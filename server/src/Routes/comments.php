<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * 评论路由
 * - 获取用户评论 (需登录)
 * - 获取视频评论 (公开)
 * - 创建评论 (需登录)
 * - 上传评论图片 (需登录)
 * - 删除评论 (仅创建者或管理员)
 */
return function (App $app, PDO $db) {

    $jwtSecret = 'videoaudit_jwt_secret_key_2026';
    $uploadDir = __DIR__ . '/../../uploads/images';

    // GET /api/comments/user/{userId} - 获取用户的所有评论 (需登录)
    $app->get('/api/comments/user/{userId}', function (Request $request, Response $response, array $args) use ($db) {
        $userId = (int) $args['userId'];

        $stmt = $db->prepare('
            SELECT c.*, u.username, v.title as video_title, v.created_at as video_created_at, uv.username as uploader
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN videos v ON c.video_id = v.id
            LEFT JOIN users uv ON v.user_id = uv.id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
        ');
        $stmt->execute([$userId]);
        $comments = $stmt->fetchAll();

        $response->getBody()->write(json_encode(['comments' => $comments]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

    // GET /api/comments/{videoId} - 获取视频评论 (公开)
    $app->get('/api/comments/{videoId}', function (Request $request, Response $response, array $args) use ($db) {
        $videoId = (int) $args['videoId'];

        $stmt = $db->prepare('
            SELECT c.*, u.username
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.video_id = ?
            ORDER BY c.timestamp ASC
        ');
        $stmt->execute([$videoId]);
        $comments = $stmt->fetchAll();

        $response->getBody()->write(json_encode(['comments' => $comments]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // POST /api/comments/upload-image - 上传评论图片 (需登录)
    $app->post('/api/comments/upload-image', function (Request $request, Response $response) use ($uploadDir) {
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['image'] ?? null;

        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode(['error' => '请上传图片文件']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // 校验文件类型
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $clientMediaType = $file->getClientMediaType();
        if (!in_array($clientMediaType, $allowedTypes)) {
            $response->getBody()->write(json_encode(['error' => '仅支持 JPG / PNG / WebP / GIF 格式']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // 限制文件大小 (10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            $response->getBody()->write(json_encode(['error' => '图片大小不能超过 10MB']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $ext = 'jpg';
        }

        $filename = uniqid('img_') . '.' . $ext;
        $file->moveTo($uploadDir . '/' . $filename);

        $response->getBody()->write(json_encode([
            'url' => '/uploads/images/' . $filename
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

    // POST /api/comments - 创建评论 (需登录)
    $app->post('/api/comments', function (Request $request, Response $response) use ($db) {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();

        $videoId = (int) ($data['video_id'] ?? 0);
        $content = trim($data['content'] ?? '');
        $timestamp = (float) ($data['timestamp'] ?? 0);
        $imageUrl = trim($data['image_url'] ?? '');
        $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;

        // 至少需要文字或图片
        if (empty($content) && empty($imageUrl)) {
            $response->getBody()->write(json_encode(['error' => '评论内容不能为空']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if ($videoId <= 0) {
            $response->getBody()->write(json_encode(['error' => '无效的视频 ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if ($timestamp < 0) {
            $response->getBody()->write(json_encode(['error' => '无效的时间戳']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // 确认视频存在
        $stmt = $db->prepare('SELECT id FROM videos WHERE id = ?');
        $stmt->execute([$videoId]);
        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => '视频不存在']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // 如果是回复，确认父评论存在
        if ($parentId !== null) {
            $stmt = $db->prepare('SELECT id FROM comments WHERE id = ?');
            $stmt->execute([$parentId]);
            if (!$stmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => '被回复的评论不存在']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('INSERT INTO comments (video_id, user_id, content, timestamp, image_url, parent_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$videoId, $user->sub, $content, $timestamp, $imageUrl ?: null, $parentId, $now]);

        $commentId = $db->lastInsertId();

        $response->getBody()->write(json_encode([
            'message' => '评论成功',
            'comment' => [
                'id' => $commentId,
                'video_id' => $videoId,
                'user_id' => $user->sub,
                'username' => $user->username,
                'content' => $content,
                'timestamp' => $timestamp,
                'image_url' => $imageUrl ?: null,
                'parent_id' => $parentId,
            ]
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

    // DELETE /api/comments/{id} - 删除评论 (仅创建者或管理员)
    $app->delete('/api/comments/{id}', function (Request $request, Response $response, array $args) use ($db, $uploadDir) {
        $user = $request->getAttribute('user');
        $commentId = (int) $args['id'];

        $stmt = $db->prepare('SELECT * FROM comments WHERE id = ?');
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();

        if (!$comment) {
            $response->getBody()->write(json_encode(['error' => '评论不存在']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // 权限检查
        if ($comment['user_id'] != $user->sub && ($user->role ?? '') !== 'admin') {
            $response->getBody()->write(json_encode(['error' => '无权删除此评论']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // 删除关联图片文件
        if (!empty($comment['image_url'])) {
            $filePath = $uploadDir . '/' . basename($comment['image_url']);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // 删除子评论
        $db->prepare('DELETE FROM comments WHERE parent_id = ?')->execute([$commentId]);
        $db->prepare('DELETE FROM comments WHERE id = ?')->execute([$commentId]);

        $response->getBody()->write(json_encode(['message' => '删除成功']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));
};
