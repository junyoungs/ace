<?php declare(strict_types=1);

/**
 * ACE Framework - A Custom PHP Framework
 *
 * @author ED
 */

// Define the project root path
define('PROJECT_ROOT', dirname(__DIR__));

// Register The Composer Auto Loader
// This handles loading all framework and application classes.
require PROJECT_ROOT . '/vendor/autoload.php';

// Load environment variables from .env file
(new \ACE\Env(PROJECT_ROOT))->load();

// Run The Application
\ACE\App::run();