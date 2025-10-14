<?php declare(strict_types=1);

// --- Define Constants & Load Env ---
define('WORKSPATH', dirname(__DIR__));
define('PROJECTPATH', WORKSPATH);

require_once WORKSPATH.'/core/Env.php';
\CORE\Env::create(WORKSPATH);

// --- Load Essential Functions & Autoloader ---
require_once WORKSPATH . '/func/default.php';
// require_once WORKSPATH . '/vendor/autoload.php'; // Assuming no composer for now

// --- Bootstrap Core Systems ---
\setRequire(WORKSPATH . '/boot/boot.php');

// --- Initialize and return the service container ---
require_once WORKSPATH . '/core/core.php';
return new \CORE\Core();