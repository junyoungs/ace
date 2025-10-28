<?php declare(strict_types=1);

namespace APP\Http\Middleware;

use ACE\Http\MiddlewareInterface;
use ACE\Auth\AuthService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\JsonResponse;

/**
 * AuthMiddleware - Validates authentication tokens
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Get token from Authorization header
        $authHeader = $request->getHeader('Authorization')[0] ?? '';

        if (empty($authHeader)) {
            return new JsonResponse(['error' => 'Missing authorization token'], 401);
        }

        // Extract token (Bearer <token>)
        if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return new JsonResponse(['error' => 'Invalid authorization format'], 401);
        }

        $token = $matches[1];

        // Validate token
        $authService = new AuthService();
        $user = $authService->getCurrentUser($token);

        if (!$user) {
            return new JsonResponse(['error' => 'Invalid or expired token'], 401);
        }

        // Store user in request attribute for later use
        $request = $request->withAttribute('auth_user', $user);

        return $next($request);
    }
}
