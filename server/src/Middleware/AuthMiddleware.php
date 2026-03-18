<?php

declare(strict_types=1);

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    private string $secret;
    private bool $adminOnly;

    public function __construct(string $secret, bool $adminOnly = false)
    {
        $this->secret = $secret;
        $this->adminOnly = $adminOnly;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorizedResponse('Missing or invalid Authorization header');
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Invalid token: ' . $e->getMessage());
        }

        // 检查是否需要管理员权限
        if ($this->adminOnly && ($decoded->role ?? '') !== 'admin') {
            return $this->forbiddenResponse('Admin access required');
        }

        // 将解码后的用户信息挂载到请求中
        $request = $request->withAttribute('user', $decoded);
        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }

    private function forbiddenResponse(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json');
    }
}
