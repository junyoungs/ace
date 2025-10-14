<?php declare(strict_types=1);

// --- Load Environment Variables ---
// Create and load the .env file.
require_once __DIR__.'/../core/Env.php';
\CORE\Env::create(dirname(__DIR__));


// --- Register The Composer Auto Loader ---
// This single line handles loading all classes and files defined in composer.json.
// You must run `composer dump-autoload` for this to work.
require_once __DIR__.'/../vendor/autoload.php';

// --- Define Constants ---
// These are defined here because boot.php (loaded by composer) might need them.
define('WORKSPATH', dirname(__DIR__));
define('PROJECTPATH', WORKSPATH);

// --- Initialize Core Service Registry ---
// The Core class's constructor registers all core services.
$core = new \CORE\Core();

// --- Return the App instance (or a container) ---
return $core;