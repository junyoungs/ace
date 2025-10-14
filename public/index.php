<?php declare(strict_types=1);

/**
 * ACE Framework - A Custom PHP Framework
 *
 * @author ED
 */

// --- Load bootstrap and get the container ---
// The app() function will handle loading the bootstrap file on its first call.
require __DIR__.'/../func/default.php';

// --- Run The Application ---
// The App::run method will use the app() helper to get services.
\APP\App::run();