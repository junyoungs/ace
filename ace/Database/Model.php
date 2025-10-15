<?php declare(strict_types=1);

namespace ACE\Database\Database;

use PDO;
use ACE\Core;

abstract class Model
{
    protected ?string $table = null;

    protected static function getTableName(): string
    {
        $instance = new static();
        if (isset($instance->table)) {
            return $instance->table;
        }
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (new \ReflectionClass(static::class))->getShortName())) . 's';
    }

    private static function getSqlComment(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? ['file' => 'unknown', 'line' => 0];
        $filePath = str_replace(PROJECT_ROOT, '', $caller['file']);
        return " /*{$filePath}:{$caller['line']}*/";
    }

    protected static function select(string $sql, array $bindings = []): array
    {
        $db = Core::getInstance()->get('Db')->driver(env('DB_CONNECTION', 'mysql'));
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
        $db = Core::getInstance()->get('Db')->driver(env('DB_CONNECTION', 'mysql'), true);
        $sql .= self::getSqlComment();
        $db->prepareQuery($sql, $bindings);
        return $db->getAffectedRows();
    }

    public static function getAll(array $columns = ['*']): array
    {
        $table = static::getTableName();
        $cols = implode(', ', $columns);
        return static::select("SELECT {$cols} FROM `{$table}`");
    }

    public static function find(int $id, array $columns = ['*']): ?array
    {
        $table = static::getTableName();
        $cols = implode(', ', $columns);
        $result = static::select("SELECT {$cols} FROM `{$table}` WHERE id = ?", [$id]);
        return $result[0] ?? null;
    }

    public static function create(array $data): int
    {
        $table = static::getTableName();
        $columns = '`' . implode('`, `', array_keys($data)) . '`';
        $placeholders = rtrim(str_repeat('?, ', count($data)), ', ');
        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        return static::statement($sql, array_values($data));
    }

    public static function update(int $id, array $data): int
    {
        $table = static::getTableName();
        $setClauses = [];
        foreach (array_keys($data) as $column) {
            $setClauses[] = "`{$column}` = ?";
        }
        $set = implode(', ', $setClauses);
        $bindings = array_merge(array_values($data), [$id]);
        $sql = "UPDATE `{$table}` SET {$set} WHERE id = ?";
        return static::statement($sql, $bindings);
    }

    public static function delete(int $id): int
    {
        $table = static::getTableName();
        return static::statement("DELETE FROM `{$table}` WHERE id = ?", [$id]);
    }
}