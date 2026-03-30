<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * 认证路由: 登录、获取当前用户、管理员用户管理
 */
return function (App $app, PDO $db) {

    $jwtSecret = 'videoaudit_jwt_secret_key_2026';
    $uploadDir = __DIR__ . '/../../uploads';

    // POST /api/auth/login
    $app->post('/api/auth/login', function (Request $request, Response $response) use ($db, $jwtSecret) {
        $data = $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            $response->getBody()->write(json_encode(['error' => '用户名和密码不能为空']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $response->getBody()->write(json_encode(['error' => '用户名或密码错误']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $payload = [
            'sub' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + 86400 * 7, // 7 天过期
        ];

        $token = JWT::encode($payload, $jwtSecret, 'HS256');

        $response->getBody()->write(json_encode([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ]
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // GET /api/auth/me
    $app->get('/api/auth/me', function (Request $request, Response $response) use ($db) {
        $user = $request->getAttribute('user');
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => '未认证']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $db->prepare('SELECT id, username, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$user->sub]);
        $userData = $stmt->fetch();

        if (!$userData) {
            $response->getBody()->write(json_encode(['error' => '用户不存在']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['user' => $userData]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

    // PUT /api/auth/password - 修改密码 (需登录)
    $app->put('/api/auth/password', function (Request $request, Response $response) use ($db) {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();
        $oldPassword = $data['old_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';

        if (empty($oldPassword) || empty($newPassword)) {
            $response->getBody()->write(json_encode(['error' => '请填写完整信息']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (strlen($newPassword) < 6) {
            $response->getBody()->write(json_encode(['error' => '新密码至少 6 个字符']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // 验证旧密码
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$user->sub]);
        $userData = $stmt->fetch();

        if (!$userData || !password_verify($oldPassword, $userData['password'])) {
            $response->getBody()->write(json_encode(['error' => '当前密码错误']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // 更新密码
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hash, $user->sub]);

        $response->getBody()->write(json_encode(['message' => '密码修改成功']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

    // ==================== 管理员用户管理 ====================

    // POST /api/admin/users - 管理员创建用户
    $app->post('/api/admin/users', function (Request $request, Response $response) use ($db) {
        $data = $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? 'user';

        if (empty($username) || empty($password)) {
            $response->getBody()->write(json_encode(['error' => '用户名和密码不能为空']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (mb_strlen($username) < 2 || mb_strlen($username) > 20) {
            $response->getBody()->write(json_encode(['error' => '用户名长度应在 2-20 个字符之间']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (strlen($password) < 6) {
            $response->getBody()->write(json_encode(['error' => '密码至少 6 个字符']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (!in_array($role, ['user', 'admin'])) {
            $role = 'user';
        }

        // 检查用户名是否已存在
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => '用户名已存在']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $hash, $role, $now]);

        $userId = $db->lastInsertId();

        $response->getBody()->write(json_encode([
            'message' => '用户创建成功',
            'user' => [
                'id' => $userId,
                'username' => $username,
                'role' => $role,
                'created_at' => $now,
            ]
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret, true));

    // GET /api/admin/users - 管理员获取用户列表
    $app->get('/api/admin/users', function (Request $request, Response $response) use ($db) {
        $stmt = $db->query('SELECT id, username, role, created_at FROM users ORDER BY created_at DESC');
        $users = $stmt->fetchAll();

        $response->getBody()->write(json_encode(['users' => $users]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret, true));

    // DELETE /api/admin/users/{id} - 管理员删除用户
    $app->delete('/api/admin/users/{id}', function (Request $request, Response $response, array $args) use ($db, $uploadDir) {
        $currentUser = $request->getAttribute('user');
        $targetId = (int) $args['id'];

        // 不允许删除自己
        if ($targetId === (int) $currentUser->sub) {
            $response->getBody()->write(json_encode(['error' => '不能删除自己的账号']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // 检查目标用户是否存在
        $stmt = $db->prepare('SELECT id, username FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch();
        if (!$targetUser) {
            $response->getBody()->write(json_encode(['error' => '用户不存在']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // 清理该用户的本地视频文件
        $stmt = $db->prepare('SELECT url, type FROM videos WHERE user_id = ?');
        $stmt->execute([$targetId]);
        $videos = $stmt->fetchAll();
        foreach ($videos as $video) {
            if ($video['type'] === 'local') {
                $filePath = $uploadDir . '/' . basename($video['url']);
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }

        // 清理该用户评论中的图片文件
        $stmt = $db->prepare('SELECT image_url FROM comments WHERE user_id = ?');
        $stmt->execute([$targetId]);
        $comments = $stmt->fetchAll();
        $imageDir = $uploadDir . '/images';
        foreach ($comments as $comment) {
            if (!empty($comment['image_url'])) {
                // image_url 可能是 JSON 数组
                $urls = json_decode($comment['image_url'], true);
                if (!is_array($urls)) {
                    $urls = [$comment['image_url']];
                }
                foreach ($urls as $imgUrl) {
                    $filePath = $imageDir . '/' . basename($imgUrl);
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
        }

        // 级联删除：评论 → 视频 → 用户
        $db->prepare('DELETE FROM comments WHERE user_id = ?')->execute([$targetId]);
        $db->prepare('DELETE FROM videos WHERE user_id = ?')->execute([$targetId]);
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);

        $response->getBody()->write(json_encode(['message' => '用户已删除']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret, true));
};
