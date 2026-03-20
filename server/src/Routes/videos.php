<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * 视频管理路由
 * - 所有登录用户可上传 / 管理自己的视频
 * - 管理员可删除任何视频
 */
return function (App $app, PDO $db) {

    $jwtSecret = 'videoaudit_jwt_secret_key_2026';
    $uploadDir = __DIR__ . '/../../uploads';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // GET /api/videos - 视频列表 (支持搜索和分页)
    $app->get('/api/videos', function (Request $request, Response $response) use ($db) {
        $queryParams = $request->getQueryParams();
        $q = $queryParams['q'] ?? '';
        $page = (int)($queryParams['page'] ?? 1);
        $limit = (int)($queryParams['limit'] ?? 20);
        $offset = ($page - 1) * $limit;

        $sql = ' FROM videos v LEFT JOIN users u ON v.user_id = u.id ';
        $params = [];
        if ($q) {
            $sql .= ' WHERE v.title LIKE ? ';
            $params[] = "%$q%";
        }

        // 获取总数
        $countStmt = $db->prepare("SELECT COUNT(*) " . $sql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // 获取分页数据
        $stmt = $db->prepare('
            SELECT v.*, u.username as uploader,
                   (SELECT COUNT(*) FROM comments c WHERE c.video_id = v.id) as comment_count
            ' . $sql . '
            ORDER BY v.created_at DESC
            LIMIT ? OFFSET ?
        ');
        
        // 绑定参数
        foreach ($params as $k => $v) {
            $stmt->bindValue($k + 1, $v);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $videos = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'videos' => $videos,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // POST /api/videos/{id}/view - 增加播放量
    $app->post('/api/videos/{id}/view', function (Request $request, Response $response, array $args) use ($db) {
        $videoId = (int) $args['id'];
        $stmt = $db->prepare('UPDATE videos SET views = views + 1 WHERE id = ?');
        $stmt->execute([$videoId]);
        $response->getBody()->write(json_encode(['message' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // GET /api/videos/all-names - 获取所有视频名称 (仅管理员, 用于过滤器)
    $app->get('/api/videos/all-names', function (Request $request, Response $response) use ($db) {
        $stmt = $db->query('SELECT id, title, url, type, (SELECT username FROM users WHERE id = v.user_id) as uploader FROM videos v ORDER BY title ASC');
        $videos = $stmt->fetchAll();
        $response->getBody()->write(json_encode(['videos' => $videos]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret, true));

    // 需要登录的视频路由组
    $app->group('/api/videos', function (RouteCollectorProxy $group) use ($db, $uploadDir) {

        // POST /api/videos/upload - 上传本地视频
        $group->post('/upload', function (Request $request, Response $response) use ($db, $uploadDir) {
            $user = $request->getAttribute('user');
            $uploadedFiles = $request->getUploadedFiles();
            $data = $request->getParsedBody();
            $title = trim($data['title'] ?? '');

            if (empty($title)) {
                $response->getBody()->write(json_encode(['error' => '请提供视频标题']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $file = $uploadedFiles['video'] ?? null;
            if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                $response->getBody()->write(json_encode(['error' => '请上传视频文件']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // 校验文件类型
            $allowedTypes = ['video/mp4', 'video/webm'];
            $clientMediaType = $file->getClientMediaType();
            if (!in_array($clientMediaType, $allowedTypes)) {
                $response->getBody()->write(json_encode(['error' => '仅支持 mp4 和 webm 格式']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // 校验文件扩展名
            $originalName = $file->getClientFilename();
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['mp4', 'webm'])) {
                $response->getBody()->write(json_encode(['error' => '仅支持 mp4 和 webm 格式']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // 限制文件大小 (500MB)
            if ($file->getSize() > 500 * 1024 * 1024) {
                $response->getBody()->write(json_encode(['error' => '文件大小不能超过 500MB']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $duration = (int)($data['duration'] ?? 0);
            $filename = uniqid('vid_') . '.' . $ext;
            $file->moveTo($uploadDir . '/' . $filename);

            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare('INSERT INTO videos (title, url, type, user_id, duration, created_at) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, '/uploads/' . $filename, 'local', $user->sub, $duration, $now]);

            $videoId = $db->lastInsertId();

            $response->getBody()->write(json_encode([
                'message' => '上传成功',
                'video' => [
                    'id' => $videoId,
                    'title' => $title,
                    'url' => '/uploads/' . $filename,
                    'type' => 'local',
                ]
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        });

        // POST /api/videos/remote - 添加远程视频 URL
        $group->post('/remote', function (Request $request, Response $response) use ($db) {
            $user = $request->getAttribute('user');
            $data = $request->getParsedBody();
            $title = trim($data['title'] ?? '');
            $url = trim($data['url'] ?? '');

            if (empty($title) || empty($url)) {
                $response->getBody()->write(json_encode(['error' => '请提供标题和视频链接']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $response->getBody()->write(json_encode(['error' => '无效的视频链接']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $duration = (int)($data['duration'] ?? 0);

            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare('INSERT INTO videos (title, url, type, user_id, duration, created_at) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $url, 'remote', $user->sub, $duration, $now]);

            $videoId = $db->lastInsertId();

            $response->getBody()->write(json_encode([
                'message' => '添加成功',
                'video' => [
                    'id' => $videoId,
                    'title' => $title,
                    'url' => $url,
                    'type' => 'remote',
                ]
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        });

        // DELETE /api/videos/{id} - 删除视频 (仅创建者或管理员)
        $group->delete('/{id}', function (Request $request, Response $response, array $args) use ($db, $uploadDir) {
            $user = $request->getAttribute('user');
            $videoId = (int) $args['id'];

            $stmt = $db->prepare('SELECT * FROM videos WHERE id = ?');
            $stmt->execute([$videoId]);
            $video = $stmt->fetch();

            if (!$video) {
                $response->getBody()->write(json_encode(['error' => '视频不存在']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // 权限检查：仅视频拥有者或管理员可删除
            if ($video['user_id'] != $user->sub && ($user->role ?? '') !== 'admin') {
                $response->getBody()->write(json_encode(['error' => '无权删除此视频']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // 如果是本地文件，删除物理文件
            if ($video['type'] === 'local') {
                $filePath = $uploadDir . '/' . basename($video['url']);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // 删除数据库记录 (级联删除关联评论)
            $db->prepare('DELETE FROM videos WHERE id = ?')->execute([$videoId]);

            $response->getBody()->write(json_encode(['message' => '删除成功']));
            return $response->withHeader('Content-Type', 'application/json');
        });

    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));
};
