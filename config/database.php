<?php declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'master' => [
                'path' => env('DB_DATABASE_PATH', __DIR__ . '/../database/database.sqlite'),
            ],
            'slave' => [
                'path' => env('DB_DATABASE_PATH', __DIR__ . '/../database/database.sqlite'),
            ],
        ],

        'mysql' => [
            'driver' => 'mysql',
            'master' => [
                [
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '3306'),
                    'database' => env('DB_DATABASE', 'ace_db'),
                    'user' => env('DB_USERNAME', 'root'),
                    'password' => env('DB_PASSWORD', ''),
                ],
            ],
            'slave' => [
                [
                    'host' => env('DB_HOST_SLAVE', env('DB_HOST', '127.0.0.1')),
                    'port' => env('DB_PORT_SLAVE', env('DB_PORT', '3306')),
                    'database' => env('DB_DATABASE_SLAVE', env('DB_DATABASE', 'ace_db')),
                    'user' => env('DB_USERNAME_SLAVE', env('DB_USERNAME', 'root')),
                    'password' => env('DB_PASSWORD_SLAVE', env('DB_PASSWORD', '')),
                ],
            ],
        ],

    ],

];