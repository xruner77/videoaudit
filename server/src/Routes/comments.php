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
return function (App $app, PDO $db, array $config) {

    $jwtSecret = $config['jwt_secret'];
    $uploadDir = __DIR__ . '/../../uploads/images';

    // GET /api/admin/comments - 获取所有评论 (管理员专用, 支持分页和搜索)
    $app->get('/api/admin/comments', function (Request $request, Response $response) use ($db) {
        $queryParams = $request->getQueryParams();
        $videoId = $queryParams['video_id'] ?? null;
        $q = $queryParams['q'] ?? '';
        $page = (int)($queryParams['page'] ?? 1);
        $limit = min(100, max(1, (int)($queryParams['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $sql = ' FROM comments c 
                LEFT JOIN users u ON c.user_id = u.id 
                LEFT JOIN videos v ON c.video_id = v.id ';
        $params = [];
        $where = [];
        if ($videoId) {
            $where[] = 'c.video_id = ?';
            $params[] = $videoId;
        }
        if ($q) {
            $where[] = '(c.content LIKE ? OR v.title LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // 获取总数
        $countStmt = $db->prepare("SELECT COUNT(*) " . $sql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // 获取分页数据
        $stmt = $db->prepare("
            SELECT c.*, u.username, v.title as video_title
            $sql
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k + 1, $v);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $comments = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'comments' => $comments,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret, true));

    // GET /api/comments/user/{userId} - 获取用户的所有评论 (需登录, 支持分页和按视频过滤)
    // 安全: 仅允许查看自己的评论（管理员可查看任意用户）
    $app->get('/api/comments/user/{userId}', function (Request $request, Response $response, array $args) use ($db) {
        $caller = $request->getAttribute('user');
        $userId = (int) $args['userId'];

        // IDOR 防护：非管理员只能查看自己的评论
        if ($userId !== (int) $caller->sub && ($caller->role ?? '') !== 'admin') {
            $response->getBody()->write(json_encode(['error' => '无权访问']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $queryParams = $request->getQueryParams();
        $page = (int)($queryParams['page'] ?? 1);
        $limit = min(100, max(1, (int)($queryParams['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $videoId = isset($queryParams['video_id']) ? (int) $queryParams['video_id'] : null;

        // 构建 WHERE 条件
        $where = 'c.user_id = ?';
        $params = [$userId];
        if ($videoId) {
            $where .= ' AND c.video_id = ?';
            $params[] = $videoId;
        }

        // 获取总数
        $countStmt = $db->prepare("SELECT COUNT(*) FROM comments c WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // 获取分页数据
        $stmt = $db->prepare("
            SELECT c.*, u.username, v.title as video_title, v.created_at as video_created_at, uv.username as uploader
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN videos v ON c.video_id = v.id
            LEFT JOIN users uv ON v.user_id = uv.id
            WHERE $where
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k + 1, $v, PDO::PARAM_INT);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $comments = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'comments' => $comments,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

    // GET /api/comments/{videoId} - 获取视频评论 (支持分页)
    $app->get('/api/comments/{videoId}', function (Request $request, Response $response, array $args) use ($db) {
        $videoId = (int) $args['videoId'];
        $queryParams = $request->getQueryParams();
        $page = (int)($queryParams['page'] ?? 1);
        $limit = min(200, max(1, (int)($queryParams['limit'] ?? 50))); // 默认 50 条，最大 200 条
        $offset = ($page - 1) * $limit;

        // 获取总数
        $countStmt = $db->prepare('SELECT COUNT(*) FROM comments WHERE video_id = ?');
        $countStmt->execute([$videoId]);
        $total = (int)$countStmt->fetchColumn();

        // 获取分页数据
        $stmt = $db->prepare('
            SELECT c.*, u.username
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.video_id = ?
            ORDER BY c.`timestamp` ASC
            LIMIT ? OFFSET ?
        ');
        $stmt->bindValue(1, $videoId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $comments = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'comments' => $comments,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

    // GET /api/comments/{videoId}/markers - 获取视频评论的所有打点信息 (不分页, 用于时间轴)
    $app->get('/api/comments/{videoId}/markers', function (Request $request, Response $response, array $args) use ($db) {
        $videoId = (int) $args['videoId'];
        $stmt = $db->prepare('
            SELECT c.id, c.`timestamp`, u.username 
            FROM comments c 
            LEFT JOIN users u ON c.user_id = u.id 
            WHERE c.video_id = ? AND c.parent_id IS NULL
            ORDER BY c.`timestamp` ASC
        ');
        $stmt->execute([$videoId]);
        $markers = $stmt->fetchAll();
        $response->getBody()->write(json_encode(['markers' => $markers]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

    // POST /api/comments/upload-image - 上传评论图片 (需登录)
    $app->post('/api/comments/upload-image', function (Request $request, Response $response) use ($uploadDir) {
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['image'] ?? null;

        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode(['error' => '请上传图片文件']));
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
        $dstPath = $uploadDir . '/' . $filename;
        $file->moveTo($dstPath);

        // 核心修复：物理文件落地后，根据其文件头部魔数流解构其实际 MIME
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($dstPath);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($realMime, $allowedTypes)) {
            @unlink($dstPath);
            $response->getBody()->write(json_encode(['error' => '内部数据安全检测拦截：禁止伪造后缀名的图片文件']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

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
        $stmt = $db->prepare('INSERT INTO comments (video_id, user_id, content, `timestamp`, image_url, parent_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
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

        // 收集所有需要删除的图片文件 (当前评论及其所有子评论)
        $filesToDelete = [];
        
        // 查找子评论
        $stmt = $db->prepare('SELECT image_url FROM comments WHERE id = ? OR parent_id = ?');
        $stmt->execute([$commentId, $commentId]);
        $relatedComments = $stmt->fetchAll();
        
        foreach ($relatedComments as $relComment) {
            if (!empty($relComment['image_url'])) {
                $urls = json_decode($relComment['image_url'], true);
                if (!is_array($urls)) {
                    $urls = [$relComment['image_url']];
                }
                foreach ($urls as $imgUrl) {
                    $filesToDelete[] = $uploadDir . '/' . basename($imgUrl);
                }
            }
        }

        // 删除子评论和当前评论 (事务保护)
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM comments WHERE parent_id = ?')->execute([$commentId]);
            $db->prepare('DELETE FROM comments WHERE id = ?')->execute([$commentId]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $response->getBody()->write(json_encode(['error' => '删除失败，请重试']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        // 数据库删除成功后，统一清理物理文件
        foreach ($filesToDelete as $filePath) {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $response->getBody()->write(json_encode(['message' => '删除成功']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));
};
