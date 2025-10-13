<?php declare(strict_types=1);

namespace APP;

use \CORE\Core;
use \CORE\QueryBuilder;
use \DATABASE\DatabaseDriverInterface;

abstract class Model
{
	public ?DatabaseDriverInterface $db = null;
	public ?string $class = null;
	public ?string $driver = null;

	/**
     * The table associated with the model.
     */
    protected ?string $table = null;

	//Public Core Classes
	public ?object $security = null;
    public ?object $crypt = null;

	public function __construct(?string $class)
	{
		$this->class	= $class;

		//Public Core Classes
		$this->security = Core::get('Security');
		$this->crypt    = Core::get('Crypt');

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
        // Guesses table name from class name: User -> users
        return strtolower(preg_replace('/(?<=\\w)(?=[A-Z])/', '_$1', basename(str_replace('\\', '/', get_called_class())))) . 's';
    }

    /**
     * Handle dynamic static method calls into the model.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        $instance = new static(get_called_class());
        $query = new QueryBuilder($instance->db);
        $query->table($instance->getTable());

        return $query->$method(...$parameters);
    }

	final private function __setDb(): void
	{
		$tmp = explode('.', $this->class);
		$this->driver = strtolower(trim((string)array_shift($tmp)));

		$this->db = Core::get('Db')->driver($this->driver); // connect slave database
		Core::get('Log')->w('INFO', 'Database Driver: '.$this->driver.':'.($this->db->isMaster ? 'master':'slave'));
	}

	final public function comment(): string
	{
		$trace = debug_backtrace();
		$call = array_shift($trace);
		$file = explode('/',$call['file']);
		$file = array_pop($file);
		$line = $call['line'];
		return ' /* '.$file.' >> Line '.$line.' */ ';
	}

	/**
	 * Executes a prepared SQL query.
	 *
	 * @return \mysqli_result|bool|\PDOStatement The result of the query.
	 */
	final public function query(string $sql, array $params = []): mixed
	{
		return $this->db->prepareQuery($sql, $params);
	}

	/**
	 * @deprecated This method is vulnerable to SQL injection. Use query() with prepared statements instead.
	 */
	final public function execute(string $sql): void
	{
		throw new \Exception("execute() is deprecated due to security risks. Use query() with prepared statements.");
	}

	/**
	 * Unit > Valid (accessible)
	 */
	final public function valid(string $valid): object
	{
		return App::singleton('valid', $valid);
	}
}