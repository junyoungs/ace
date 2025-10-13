<?php declare(strict_types=1);

/**
 * Simple Database Migration Runner
 */

// Bootstrap the framework for CLI environment
require __DIR__ . '/bootstrap/app.php';

// Get the database connection
try {
    // In CLI, we might want to specify the connection, but for now, we use the default.
    $db = \CORE\Core::get('Db')->driver('mysql', true);
    echo "Database connection successful.\n";
} catch (\Exception $e) {
    echo "Error connecting to database: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Ensure the migrations table exists.
 */
function ensure_migrations_table_exists(\DATABASE\DatabaseDriverInterface $db): void
{
    $tableName = 'migrations';
    // Check if table exists
    $result = $db->prepareQuery("SHOW TABLES LIKE ?", [$tableName]);

    $rowCount = ($result instanceof \PDOStatement) ? $result->rowCount() : $result->num_rows;

    if ($rowCount == 0) {
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

$migrationsData = ($result instanceof \PDOStatement) ? $result->fetchAll(\PDO::FETCH_ASSOC) : $result->fetch_all(MYSQLI_ASSOC);
foreach ($migrationsData as $row) {
    $executedMigrations[] = $row['migration'];
}

// 2. Scan migration files
$migrationPath = __DIR__ . '/database/migrations';
$migrationFiles = scandir($migrationPath);
$pendingMigrations = [];

foreach ($migrationFiles as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $migrationNameFromFile = pathinfo($file, PATHINFO_FILENAME);
        if (!in_array($migrationNameFromFile, $executedMigrations)) {
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
$lastBatch = ($batchResult instanceof \PDOStatement) ? $batchResult->fetchColumn() : ($batchResult->fetch_assoc()['max_batch'] ?? 0);
$nextBatch = (int)$lastBatch + 1;

foreach ($pendingMigrations as $file) {
    $filePath = $migrationPath . '/' . $file;
    require_once $filePath;

    $migrationNameFromFile = pathinfo($file, PATHINFO_FILENAME);

    // Extract class name from file name (e.g., 2025_10_13_013400_create_users_table -> CreateUsersTable)
    $classNameString = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $migrationNameFromFile);
    $className = str_replace('_', '', ucwords($classNameString, '_'));

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