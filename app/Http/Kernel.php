<?php declare(strict_types=1);

namespace APP\Http;

class Kernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     */
    protected array $middleware = [
        // Example: \APP\Http\Middleware\EncryptCookies::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * The key is the subdomain. 'api' will match 'api.yourdomain.com'.
     * The '*' key is a wildcard and applies to all subdomains if no specific group matches.
     */
    protected array $middlewareGroups = [
        'api' => [
            \APP\Http\Middleware\Authenticate::class,
        ],

        'admin' => [
            // \APP\Http\Middleware\AuthenticateAdmin::class,
        ],
    ];
}