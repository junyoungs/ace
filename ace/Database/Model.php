<?php declare(strict_types=1);

namespace ACE\Database;

use PDO;

abstract class Model
{
    protected ?string $table = null;

    protected static function getTableName(): string
    {
        if (isset(static::$table)) {
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
}