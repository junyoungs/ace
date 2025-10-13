<?php

/**
 * Simple Database Migration Runner
 */

// --- Bootstrap The Framework ---
// This is a simplified bootstrap process for the CLI environment.
define('WORKSPATH', __DIR__);
// We need to define PROJECTPATH, assuming it's the same as WORKSPATH for now.
define('PROJECTPATH', __DIR__);

// Required for path definitions in boot.php
// In a real CLI app, this would be handled more robustly.
$_SERVER['HTTP_HOST'] = 'localhost';
require_once WORKSPATH . DIRECTORY_SEPARATOR . 'func' . DIRECTORY_SEPARATOR . 'default.php';
\setRequire(WORKSPATH . DIRECTORY_SEPARATOR . 'boot' . DIRECTORY_SEPARATOR . 'boot.php');
\setRequire(WORKSPATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'core.php');
\setRequire(WORKSPATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'app.php');
// --- End Bootstrap ---


// Get the database connection
try {
    $db = \CORE\Core::get('Db')->driver('mysql', true); // Get master connection
    echo "Database connection successful.\n";
} catch (\Exception $e) {
    echo "Error connecting to database: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Ensure the migrations table exists.
 */
function ensure_migrations_table_exists($db)
{
    $tableName = 'migrations';
    // Check if table exists
    $result = $db->prepareQuery("SHOW TABLES LIKE ?", [$tableName]);

    if ($result->rowCount() == 0) {
        echo "Migrations table not found. Creating table: {$tableName}\n";
        try {
            $createQuery = "
                CREATE TABLE `{$tableName}` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `migration` VARCHAR(255) NOT NULL,
                    `batch` INT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ";
            // Use query for DDL statements as they can't be prepared in some drivers
            $db->query($createQuery);
            echo "Table '{$tableName}' created successfully.\n";
        } catch (\Exception $e) {
            echo "Failed to create migrations table: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

ensure_migrations_table_exists($db);

// --- Main Migration Logic ---

// 1. Get executed migrations
$executedMigrations = [];
$result = $db->prepareQuery("SELECT migration FROM migrations");
if ($result && $result->rowCount() > 0) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $executedMigrations[] = $row['migration'];
    }
}

// 2. Scan migration files
$migrationPath = __DIR__ . '/database/migrations';
$migrationFiles = scandir($migrationPath);
$pendingMigrations = [];

foreach ($migrationFiles as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $migrationName = pathinfo($file, PATHINFO_FILENAME);
        if (!in_array($migrationName, $executedMigrations)) {
            $pendingMigrations[] = $file;
        }
    }
}

if (empty($pendingMigrations)) {
    echo "No new migrations to run.\n";
    exit(0);
}

// 3. Run pending migrations
echo "Found " . count($pendingMigrations) . " new migrations to run.\n";

// Get the next batch number
$batchResult = $db->prepareQuery("SELECT MAX(batch) as max_batch FROM migrations");
$lastBatch = $batchResult->fetchColumn() ?? 0;
$nextBatch = $lastBatch + 1;

foreach ($pendingMigrations as $file) {
    $filePath = $migrationPath . '/' . $file;
    require_once $filePath;

    $migrationNameFromFile = pathinfo($file, PATHINFO_FILENAME);

    // Extract class name from file name (e.g., 2025_10_13_013400_CreateUsersTable -> CreateUsersTable)
    $className = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $migrationNameFromFile);

    if (!empty($className) && class_exists($className)) {
        try {
            echo "Migrating: {$migrationNameFromFile}\n";
            $migration = new $className();
            $migration->db = $db; // Inject DB connection
            $migration->up();

            // Record the migration
            $db->prepareQuery("INSERT INTO migrations (migration, batch) VALUES (?, ?)", [$migrationNameFromFile, $nextBatch]);
            echo "Migrated:  {$migrationNameFromFile}\n";
        } catch (\Exception $e) {
            echo "ERROR migrating {$migrationNameFromFile}: " . $e->getMessage() . "\n";
            // Simple rollback logic: just exit. A real system might have transactions.
            exit(1);
        }
    }
}

echo "Migration process completed successfully.\n";