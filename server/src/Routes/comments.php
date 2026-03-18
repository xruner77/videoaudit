<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * 评论路由
 * - 获取视频评论 (公开)
 * - 创建评论 (需登录)
 * - 删除评论 (仅创建者或管理员)
 */
return function (App $app, PDO $db) {

    $jwtSecret = 'videoaudit_jwt_secret_key_2026';

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

    // POST /api/comments - 创建评论 (需登录)
    $app->post('/api/comments', function (Request $request, Response $response) use ($db) {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();

        $videoId = (int) ($data['video_id'] ?? 0);
        $content = trim($data['content'] ?? '');
        $timestamp = (float) ($data['timestamp'] ?? 0);

        if (empty($content)) {
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

        $stmt = $db->prepare('INSERT INTO comments (video_id, user_id, content, timestamp) VALUES (?, ?, ?, ?)');
        $stmt->execute([$videoId, $user->sub, $content, $timestamp]);

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
            ]
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

    // DELETE /api/comments/{id} - 删除评论 (仅创建者或管理员)
    $app->delete('/api/comments/{id}', function (Request $request, Response $response, array $args) use ($db) {
        $user = $request->getAttribute('user');
        $commentId = (int) $args['id'];

        $stmt = $db->prepare('SELECT * FROM comments WHERE id = ?');
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();

        if (!$comment) {
            $response->getBody()->write(json_encode(['error' => '评论不存在']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // 权限检查：仅评论创建者或管理员可删除
        if ($comment['user_id'] != $user->sub && ($user->role ?? '') !== 'admin') {
            $response->getBody()->write(json_encode(['error' => '无权删除此评论']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $db->prepare('DELETE FROM comments WHERE id = ?')->execute([$commentId]);

        $response->getBody()->write(json_encode(['message' => '删除成功']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));
};
