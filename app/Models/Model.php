<?php declare(strict_types=1);

namespace APP\Models;

use \CORE\Core;
use \DATABASE\DatabaseDriverInterface;
use \PDO;

abstract class Model
{
	public ?DatabaseDriverInterface $db = null;
	public ?string $class = null;
	public ?string $driver = null;

	/**
     * The table associated with the model.
     */
    protected ?string $table = null;

	public function __construct(?string $class)
	{
		$this->class = $class;
		$this->__setDb();
	}

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', basename(str_replace('\\', '/', get_called_class())))) . 's';
    }

	final private function __setDb(): void
	{
		$tmp = explode('.', $this->class ?? '');
		if(!empty($tmp[0])) $this->driver = strtolower(trim((string)array_shift($tmp)));
		else $this->driver = 'mysql';

		$this->db = app('Db')->driver($this->driver);
	}

    /**
     * Generates a comment with the file and line number of the caller.
     */
    private static function getSqlComment(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        // index 2 should be the actual caller of select() or statement()
        $caller = $trace[2] ?? ['file' => 'unknown', 'line' => 0];
        $filePath = str_replace(WORKSPATH, '', $caller['file']); // Get relative path
        return " /*{$filePath}:{$caller['line']}*/";
    }

    /**
     * Executes a SELECT query safely with bindings and returns the results.
     */
    public static function select(string $sql, array $bindings = []): array
    {
        $instance = new static(get_called_class());
        $sql .= self::getSqlComment();
        $result = $instance->db->prepareQuery($sql, $bindings);

        if ($result instanceof \PDOStatement) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        } elseif ($result instanceof \mysqli_result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        return [];
    }

    /**
     * Executes a non-SELECT statement (INSERT, UPDATE, DELETE) safely.
     */
    public static function statement(string $sql, array $bindings = []): int
    {
        $instance = new static(get_called_class());
        $sql .= self::getSqlComment();
        $instance->db->prepareQuery($sql, $bindings);
        return $instance->db->getAffectedRows();
    }
}