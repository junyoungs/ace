<?php declare(strict_types=1);

namespace ACE\Database;

use PDO;

abstract class Model
{
    protected static string $table = '';
    protected static array $fillable = [];

    protected static function getTableName(): string
    {
        if (!empty(static::$table)) {
            return static::$table;
        }
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (new \ReflectionClass(static::class))->getShortName())) . 's';
    }

    private static function getSqlComment(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? ['file' => 'unknown', 'line' => 0];
        $filePath = str_replace(BASE_PATH, '', $caller['file']);
        return " /*{$filePath}:{$caller['line']}*/";
    }

    protected static function select(string $sql, array $bindings = []): array
    {
        /** @var Db $dbManager */
        $dbManager = app(Db::class);
        $db = $dbManager->driver(env('DB_CONNECTION', 'mysql'));
        $sql .= self::getSqlComment();
        $result = $db->prepareQuery($sql, $bindings);

        if ($result instanceof \PDOStatement) {
            return $result->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($result instanceof \mysqli_result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        return [];
    }

    protected static function statement(string $sql, array $bindings = []): int
    {
        /** @var Db $dbManager */
        $dbManager = app(Db::class);
        $db = $dbManager->driver(env('DB_CONNECTION', 'mysql'), true);
        $sql .= self::getSqlComment();
        $db->prepareQuery($sql, $bindings);
        return $db->getAffectedRows();
    }

    // ========================================
    // CRUD Operations
    // ========================================

    /**
     * Get all records
     */
    public static function getAll(): array
    {
        $table = static::getTableName();
        return static::select("SELECT * FROM {$table}");
    }

    /**
     * Find a record by ID
     */
    public static function find(int $id): ?array
    {
        $table = static::getTableName();
        $results = static::select("SELECT * FROM {$table} WHERE id = ? LIMIT 1", [$id]);
        return $results[0] ?? null;
    }

    /**
     * Find records where column = value
     */
    public static function where(string $column, mixed $value): array
    {
        $table = static::getTableName();
        return static::select("SELECT * FROM {$table} WHERE {$column} = ?", [$value]);
    }

    /**
     * Create a new record
     */
    public static function create(array $data): int
    {
        $table = static::getTableName();

        // Filter only fillable fields
        if (!empty(static::$fillable)) {
            $data = array_intersect_key($data, array_flip(static::$fillable));
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        static::statement(
            "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})",
            array_values($data)
        );

        /** @var Db $dbManager */
        $dbManager = app(Db::class);
        $db = $dbManager->driver(env('DB_CONNECTION', 'mysql'), true);
        return $db->getLastInsertId();
    }

    /**
     * Update a record by ID
     */
    public static function update(int $id, array $data): int
    {
        $table = static::getTableName();

        // Filter only fillable fields
        if (!empty(static::$fillable)) {
            $data = array_intersect_key($data, array_flip(static::$fillable));
        }

        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "{$column} = ?";
        }
        $setClause = implode(', ', $sets);

        $bindings = array_values($data);
        $bindings[] = $id;

        return static::statement(
            "UPDATE {$table} SET {$setClause} WHERE id = ?",
            $bindings
        );
    }

    /**
     * Delete a record by ID
     */
    public static function delete(int $id): int
    {
        $table = static::getTableName();
        return static::statement("DELETE FROM {$table} WHERE id = ?", [$id]);
    }

    /**
     * Execute a raw query and return results
     */
    public static function query(string $sql, array $bindings = []): array
    {
        return static::select($sql, $bindings);
    }

    /**
     * Execute a raw statement and return affected rows
     */
    public static function execute(string $sql, array $bindings = []): int
    {
        return static::statement($sql, $bindings);
    }
}