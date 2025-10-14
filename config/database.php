<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work.
    |
    */

    'default' => 'mysql',

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'master' => [
                'path' => __DIR__ . '/../database/database.sqlite',
            ],
            'slave' => [
                'path' => __DIR__ . '/../database/database.sqlite',
            ],
        ],

        'mysql' => [
            'driver' => 'mysql',
            'master' => [
                [
                    'host' => '127.0.0.1',
                    'port' => '3306',
                    'database' => 'framework_db',
                    'user' => 'root',
                    'password' => '',
                ],
            ],
            'slave' => [
                [
                    'host' => '127.0.0.1',
                    'port' => '3306',
                    'database' => 'framework_db_slave',
                    'user' => 'root',
                    'password' => '',
                ],
            ],
        ],

    ],

];