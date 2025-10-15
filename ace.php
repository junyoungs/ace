#!/usr/bin/env php
<?php declare(strict_types=1);

define('BASE_PATH', __DIR__);
require BASE_PATH . '/vendor/autoload.php';
(new \ACE\Support\Env(BASE_PATH))->load();
require_once BASE_PATH . '/ace/Support/boot.php';

use \ACE\Database\DatabaseDriverInterface;
use \PDO;

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
    case 'migrate': run_migrations(); break;
    case 'docs:generate': generate_api_docs(); break;
    case 'make:api':
        $name = $args[1] ?? null;
        if (!$name) { exit("Error: Missing resource name for make:api command.\n"); }
        make_api_resource($name);
        break;
    case 'serve': start_server(); break;
    default: exit("Error: Command '{$command}' not found.\n");
}

function make_api_resource(string $name)
{
    echo "Creating API resource for '{$name}'...\n";

    $modelName = ucfirst($name);
    $controllerName = $modelName . 'Controller';
    $defaultTableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';

    echo "What is the table name for this resource? (default: {$defaultTableName}): ";
    $tableNameInput = trim(fgets(STDIN));
    $tableName = !empty($tableNameInput) ? $tableNameInput : $defaultTableName;

    $variableName = lcfirst($modelName);

    $replacements = [
        '{{className}}' => $controllerName,
        '{{modelName}}' => $modelName,
        '{{tableName}}' => $tableName,
        '{{variableName}}' => $variableName,
    ];

    // Create Migration
    $migrationClassName = 'Create' . str_replace(' ', '', ucwords(str_replace('_', ' ', $tableName))) . 'Table';
    $migrationContent = str_replace(
        ['{{className}}', '{{tableName}}'],
        [$migrationClassName, $tableName],
        file_get_contents(__DIR__ . '/stubs/migration.create.stub')
    );
    $migrationFileName = date('Y_m_d_His') . "_{$migrationClassName}.php";
    file_put_contents(__DIR__ . "/database/migrations/{$migrationFileName}", $migrationContent);
    echo "Created Migration: database/migrations/{$migrationFileName}\n";

    // Create Model
    $modelContent = str_replace(
        ['{{className}}', '{{tableName}}'],
        [$modelName, $tableName],
        file_get_contents(__DIR__ . '/stubs/model.stub')
    );
    if (!is_dir(__DIR__ . '/app/Models')) mkdir(__DIR__ . '/app/Models', 0755, true);
    file_put_contents(__DIR__ . "/app/Models/{$modelName}.php", $modelContent);
    echo "Created Model: app/Models/{$modelName}.php\n";

    // Create Service
    $serviceName = $modelName . 'Service';
    $serviceContent = str_replace(
        ['{{className}}', '{{modelName}}'],
        [$serviceName, $modelName],
        file_get_contents(__DIR__ . '/stubs/service.stub')
    );
    if (!is_dir(__DIR__ . '/app/Services')) mkdir(__DIR__ . '/app/Services', 0755, true);
    file_put_contents(__DIR__ . "/app/Services/{$serviceName}.php", $serviceContent);
    echo "Created Service: app/Services/{$serviceName}.php\n";

    // Create Controller
    $replacements['{{serviceName}}'] = $serviceName;
    $replacements['{{serviceVariableName}}'] = lcfirst($serviceName);
    $controllerContent = str_replace(
        array_keys($replacements),
        array_values($replacements),
        file_get_contents(__DIR__ . '/stubs/controller.api.stub')
    );
    if (!is_dir(__DIR__ . '/app/Http/Controllers')) mkdir(__DIR__ . '/app/Http/Controllers', 0755, true);
    file_put_contents(__DIR__ . "/app/Http/Controllers/{$controllerName}.php", $controllerContent);
    echo "Created Controller: app/Http/Controllers/{$controllerName}.php\n";

    echo "API resource for '{$name}' created successfully.\n";
}

// ... other functions
function generate_api_docs() { /* ... */ }
function run_migrations() { /* ... */ }
function ensure_migrations_table_exists(DatabaseDriverInterface $db): void { /* ... */ }
function start_server() { /* ... */ }
function get_class_from_file(string $path): ?string { /* ... */ }