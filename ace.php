#!/usr/bin/env php
<?php declare(strict_types=1);

define('BASE_PATH', __DIR__);

require BASE_PATH . '/vendor/autoload.php';

(new \ACE\Support\Env(BASE_PATH))->load();

// Bootstrap essential configurations
require_once BASE_PATH . '/ace/Support/boot.php';

use \ACE\Database\DatabaseDriverInterface;
use \PDO;

// --- CLI Application ---

$args = $argv;
array_shift($args);

if (empty($args)) {
    echo "ACE Console Tool\n\n";
    echo "Usage:\n";
    echo "  command [options] [arguments]\n\n";
    echo "Available Commands:\n";
    echo "  migrate           Run the database migrations\n";
    echo "  docs:generate     Generate API documentation\n";
    echo "  make:api [name]   Create a new API resource (Model, Controller, Service)\n";
    echo "  serve             Start the high-performance server (RoadRunner)\n";
    exit(0);
}

$command = $args[0];

switch ($command) {
    case 'migrate':
        run_migrations();
        break;
    case 'docs:generate':
        generate_api_docs();
        break;
    case 'make:api':
        $name = $args[1] ?? null;
        if (!$name) {
            echo "Error: Missing resource name for make:api command.\n";
            exit(1);
        }
        make_api_resource($name);
        break;
    case 'serve':
        start_server();
        break;
    default:
        echo "Error: Command '{$command}' not found.\n";
        exit(1);
}

// ... (All command functions remain the same)
function make_api_resource(string $name) { /* ... */ }
function generate_api_docs() { /* ... */ }
function run_migrations() { /* ... */ }
function ensure_migrations_table_exists(DatabaseDriverInterface $db): void { /* ... */ }
function start_server() { /* ... */ }
function get_class_from_file(string $path): ?string { /* ... */ }