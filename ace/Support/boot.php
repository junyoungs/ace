<?php declare(strict_types=1);

namespace ACE\Support;

/**
 * Determine the application environment.
 *
 * The environment is determined by the APP_ENV variable in your .env file.
 * If it's not set, it defaults to 'production'.
 */
$appEnv = $_ENV['APP_ENV'] ?? 'production';

define('MODE', $appEnv);

if (MODE === 'dev' || MODE === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

/**
 * Define Host.
 * We still need this for path resolution, but it's simplified.
 */
$host = explode('.', strtolower($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('HOST', $host[0]);

/**
 * Load default functions and error handlers
 */
require_once __DIR__ . '/default.php';
require_once __DIR__ . '/handler.php';