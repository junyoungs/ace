<?php declare(strict_types=1);

/**
 * ACE Framework
 * @author ED
 */

define('BASE_PATH', dirname(__DIR__));

// Composer Autoloader
require BASE_PATH . '/vendor/autoload.php';

// Load Environment Variables
(new \ACE\Env(BASE_PATH))->load();

// Bootstrap essential configurations
require_once BASE_PATH . '/ace/Support/boot.php';

// Run The Application
\ACE\Kernel::run();