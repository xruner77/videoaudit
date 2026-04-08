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
return function (App $app, PDO $db, array $config) {

    $jwtSecret = $config['jwt_secret'];
    $uploadDir = __DIR__ . '/../../uploads';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // GET /api/videos - 视频列表 (支持搜索和分页)
    $app->get('/api/videos', function (Request $request, Response $response) use ($db) {
        $queryParams = $request->getQueryParams();
        $q = $queryParams['q'] ?? '';
        $page = (int)($queryParams['page'] ?? 1);
        $limit = min(100, max(1, (int)($queryParams['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $sql = ' FROM videos v LEFT JOIN users u ON v.user_id = u.id ';
        $params = [];
        $where = [];
        
        if ($q) {
            $where[] = 'v.title LIKE ?';
            $params[] = "%$q%";
        }
        
        $userId = $queryParams['user_id'] ?? null;
        if ($userId) {
            $where[] = 'v.user_id = ?';
            $params[] = $userId;
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
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
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));


    // POST /api/videos/{id}/view - 增加播放量
    $app->post('/api/videos/{id}/view', function (Request $request, Response $response, array $args) use ($db) {
        $videoId = (int) $args['id'];
        $stmt = $db->prepare('UPDATE videos SET views = views + 1 WHERE id = ?');
        $stmt->execute([$videoId]);
        $response->getBody()->write(json_encode(['message' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

    // GET /api/videos/all-names - 获取所有视频名称 (仅管理员, 用于过滤器)
    $app->get('/api/videos/all-names', function (Request $request, Response $response) use ($db) {
        $stmt = $db->query('SELECT id, title, url, type, (SELECT username FROM users WHERE id = v.user_id) as uploader, (SELECT COUNT(*) FROM comments WHERE video_id = v.id) as comment_count FROM videos v ORDER BY title ASC');
        $videos = $stmt->fetchAll();
        $response->getBody()->write(json_encode(['videos' => $videos]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret, true));

    // GET /api/videos/{id} - 获取单个视频详情 (必须放在 /all-names 之后，避免通配符拦截)
    $app->get('/api/videos/{id}', function (Request $request, Response $response, array $args) use ($db) {
        $videoId = (int) $args['id'];

        $stmt = $db->prepare('
            SELECT v.*, u.username as uploader,
                   (SELECT COUNT(*) FROM comments c WHERE c.video_id = v.id) as comment_count
            FROM videos v
            LEFT JOIN users u ON v.user_id = u.id
            WHERE v.id = ?
        ');
        $stmt->execute([$videoId]);
        $video = $stmt->fetch();

        if (!$video) {
            $response->getBody()->write(json_encode(['error' => '视频不存在']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['video' => $video]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

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

            // 校验文件扩展名
            $originalName = $file->getClientFilename();
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['mp4', 'webm'])) {
                $response->getBody()->write(json_encode(['error' => '仅支持 mp4 和 webm 格式']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // 限制文件大小 (50MB)
            if ($file->getSize() > 50 * 1024 * 1024) {
                $response->getBody()->write(json_encode(['error' => '文件大小不能超过 50MB']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $duration = (int)($data['duration'] ?? 0);
            $filename = uniqid('vid_') . '.' . $ext;
            $dstPath = $uploadDir . '/' . $filename;
            $file->moveTo($dstPath);

            // 核心修复：彻底校验文件真实的实体 MIME 类型（防恶意篡改外层 Content-Type）
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($dstPath);
            $allowedTypes = ['video/mp4', 'video/webm'];
            if (!in_array($realMime, $allowedTypes)) {
                @unlink($dstPath);
                $response->getBody()->write(json_encode(['error' => '内部数据安全检测拦截：文件魔数异常，请上传真实的视频文件！']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

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

            // 获取需要清理的物理文件 (视频文件 + 关联评论的图片文件)
            $filesToDelete = [];
            
            // 1. 记录本地视频文件
            if ($video['type'] === 'local') {
                $filesToDelete[] = $uploadDir . '/' . basename($video['url']);
            }
            
            // 2. 记录该视频下所有评论的关联图片文件
            $stmt = $db->prepare('SELECT image_url FROM comments WHERE video_id = ? AND image_url IS NOT NULL');
            $stmt->execute([$videoId]);
            $comments = $stmt->fetchAll();
            $imageDir = $uploadDir . '/images';
            foreach ($comments as $comment) {
                if (!empty($comment['image_url'])) {
                    $urls = json_decode($comment['image_url'], true);
                    if (!is_array($urls)) {
                        $urls = [$comment['image_url']];
                    }
                    foreach ($urls as $imgUrl) {
                        $filesToDelete[] = $imageDir . '/' . basename($imgUrl);
                    }
                }
            }

            // 删除数据库记录 (依靠外键 CASCADE 级联删除评论)
            try {
                $db->prepare('DELETE FROM videos WHERE id = ?')->execute([$videoId]);
            } catch (\Exception $e) {
                $response->getBody()->write(json_encode(['error' => '删除视频记录失败，请重试']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }

            // 数据库记录删除成功后，清理物理文件
            foreach ($filesToDelete as $filePath) {
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            $response->getBody()->write(json_encode(['message' => '删除成功']));
            return $response->withHeader('Content-Type', 'application/json');
        });

    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));
};
