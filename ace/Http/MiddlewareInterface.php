<?php declare(strict_types=1);

namespace ACE\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface MiddlewareInterface
{
    /**
     * Process an incoming server request.
     *
     * @param ServerRequestInterface $request
     * @param callable $next The next middleware in the chain.
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface;
}