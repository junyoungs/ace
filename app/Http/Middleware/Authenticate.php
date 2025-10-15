<?php declare(strict_types=1);

namespace APP\Http\Middleware;

use ACE\Http\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\JsonResponse;

class Authenticate implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     */
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');

        // This is a very basic example.
        // In a real app, you would parse a JWT or query a database.
        if ($authHeader !== 'Bearer my-secret-token') {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        // If authentication passes, proceed to the next middleware or controller.
        return $next($request);
    }
}