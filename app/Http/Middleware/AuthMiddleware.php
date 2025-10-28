<?php declare(strict_types=1);

namespace APP\Http\Middleware;

use ACE\Http\MiddlewareInterface;
use ACE\Auth\AuthService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * AuthMiddleware - Validates authentication tokens
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(ServerRequestInterface $request): ServerRequestInterface
    {
        // Get token from Authorization header
        $authHeader = $request->getHeader('Authorization')[0] ?? '';

        if (empty($authHeader)) {
            $this->unauthorized('Missing authorization token');
        }

        // Extract token (Bearer <token>)
        if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            $this->unauthorized('Invalid authorization format');
        }

        $token = $matches[1];

        // Validate token
        $authService = new AuthService();
        $user = $authService->getCurrentUser($token);

        if (!$user) {
            $this->unauthorized('Invalid or expired token');
        }

        // Store user in request attribute for later use
        $request = $request->withAttribute('auth_user', $user);

        return $request;
    }

    private function unauthorized(string $message): void
    {
        http_response_code(401);
        echo json_encode(['error' => $message]);
        exit;
    }
}
