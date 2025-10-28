#!/usr/bin/env php
<?php declare(strict_types=1);

define('BASE_PATH', __DIR__);
require BASE_PATH . '/vendor/autoload.php';
(new \ACE\Support\Env(BASE_PATH))->load();
require_once BASE_PATH . '/ace/Support/boot.php';

use ACE\Database\DatabaseDriverInterface;
use ACE\Database\DbmlParser;
use ACE\Database\CodeGenerator;

$args = $argv;
array_shift($args);

if (empty($args)) {
    showHelp();
    exit(0);
}

$command = $args[0];

switch ($command) {
    case 'api':
        $dbmlPath = $args[1] ?? BASE_PATH . '/database/schema.dbml';
        generateApi($dbmlPath);
        break;
    case 'migrate':
        runMigrations();
        break;
    case 'serve':
        startServer();
        break;
    default:
        echo "Error: Unknown command '{$command}'\n\n";
        showHelp();
        exit(1);
}

function showHelp(): void
{
    echo <<<HELP
ACE Framework - Schema-First API Generator

Usage:
  ./ace <command> [options]

Commands:
  api [path]       Generate complete API from DBML schema
                   Default path: database/schema.dbml

  migrate          Run database migrations

  serve            Start development server

Examples:
  ./ace api                          Generate from default schema
  ./ace api database/custom.dbml     Generate from custom schema
  ./ace migrate                      Run all pending migrations
  ./ace serve                        Start server on port 8080

HELP;
}

function generateApi(string $dbmlPath): void
{
    if (!file_exists($dbmlPath)) {
        exit("Error: DBML file not found: {$dbmlPath}\n");
    }

    echo "========================================\n";
    echo "ACE - API Generator\n";
    echo "========================================\n\n";
    echo "Reading: {$dbmlPath}\n\n";

    $dbmlContent = file_get_contents($dbmlPath);
    $parser = new DbmlParser();
    $schema = $parser->parse($dbmlContent);

    $tableCount = count($schema['tables']);
    $relationCount = count($schema['relationships']);

    echo "Found: {$tableCount} tables, {$relationCount} relationships\n\n";

    foreach ($schema['tables'] as $tableName => $tableData) {
        $columnCount = count($tableData['columns']);
        echo "  • {$tableName} ({$columnCount} columns)\n";
    }

    echo "\nGenerating...\n\n";

    $generator = new CodeGenerator();
    $generator->generate($schema, $parser);

    echo "\n========================================\n";
    echo "✓ Complete!\n";
    echo "========================================\n\n";
    echo "Next steps:\n";
    echo "  ./ace migrate    # Create database tables\n";
    echo "  ./ace serve      # Start server\n\n";
}

function runMigrations(): void
{
    echo "Running migrations...\n\n";

    $migrationPath = BASE_PATH . '/database/migrations';

    if (!is_dir($migrationPath)) {
        echo "No migrations directory found.\n";
        return;
    }

    $db = getDbConnection();
    ensureMigrationsTable($db);

    $files = glob($migrationPath . '/*.php');
    if (empty($files)) {
        echo "No migration files found.\n";
        return;
    }

    sort($files);
    $executed = $db->query("SELECT migration FROM migrations")->fetchAll(\PDO::FETCH_COLUMN);

    $ranCount = 0;
    foreach ($files as $file) {
        $filename = basename($file);

        if (in_array($filename, $executed)) {
            continue;
        }

        echo "Running: {$filename}... ";

        require_once $file;
        $className = getClassNameFromFile($file);

        if (!$className || !class_exists($className)) {
            echo "SKIP (no class found)\n";
            continue;
        }

        $migration = new $className();
        $migration->up($db);

        $db->exec("INSERT INTO migrations (migration, batch) VALUES ('{$filename}', 1)");

        echo "OK\n";
        $ranCount++;
    }

    echo "\n";
    if ($ranCount > 0) {
        echo "✓ {$ranCount} migrations executed\n";
    } else {
        echo "✓ Nothing to migrate\n";
    }
}

function ensureMigrationsTable(DatabaseDriverInterface $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL,
        batch INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function getDbConnection(): DatabaseDriverInterface
{
    $dbManager = app(\ACE\Database\Db::class);
    return $dbManager->driver(env('DB_CONNECTION', 'mysql'));
}

function getClassNameFromFile(string $path): ?string
{
    $contents = file_get_contents($path);

    if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
        return $matches[1];
    }

    return null;
}

function startServer(): void
{
    echo "Starting development server...\n";
    echo "Server running at: http://localhost:8080\n";
    echo "Press Ctrl+C to stop\n\n";

    $publicPath = BASE_PATH . '/public';

    if (!is_dir($publicPath)) {
        exit("Error: Public directory not found\n");
    }

    chdir($publicPath);
    passthru('php -S localhost:8080 2>&1');
}
