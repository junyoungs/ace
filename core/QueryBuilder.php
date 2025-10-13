<?php

namespace CORE;

class QueryBuilder
{
    /**
     * The database connection instance.
     *
     * @var \stdClass
     */
    protected $db;

    /**
     * The table which the query is targeting.
     *
     * @var string
     */
    protected $table;

    /**
     * The columns that should be returned.
     *
     * @var array
     */
    public $columns = ['*'];

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $wheres = [];

    /**
     * The parameters for the query bindings.
     *
     * @var array
     */
    public $bindings = [];

    public function __construct($connection)
    {
        $this->db = $connection;
    }

    /**
     * Set the table for the query.
     *
     * @param string $table
     * @return $this
     */
    public function table($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param string $column
     * @param string $operator
     * @param mixed  $value
     * @return $this
     */
    public function where($column, $operator, $value)
    {
        $this->wheres[] = "`{$column}` {$operator} ?";
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param int $id
     * @return array|null
     */
    public function find($id)
    {
        return $this->where('id', '=', $id)->first();
    }

    /**
     * Execute the query and get the first result.
     *
     * @return array|null
     */
    public function first()
    {
        $results = $this->get();
        return count($results) > 0 ? $results[0] : null;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @return array
     */
    public function get()
    {
        $sql = "SELECT " . implode(', ', $this->columns) . " FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        $result = $this->db->prepareQuery($sql, $this->bindings);

        $data = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array|mixed  $columns
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        $columns = '`' . implode('`, `', array_keys($values)) . '`';
        $placeholders = rtrim(str_repeat('?, ', count($values)), ', ');

        $sql = "INSERT INTO `{$this->table}` ({$columns}) VALUES ({$placeholders})";

        $result = $this->db->prepareQuery($sql, array_values($values));
        return $result !== false;
    }

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int The number of affected rows.
     */
    public function update(array $values)
    {
        $setClauses = [];
        $updateBindings = [];
        foreach ($values as $column => $value) {
            $setClauses[] = "`{$column}` = ?";
            $updateBindings[] = $value;
        }
        $set = implode(', ', $setClauses);

        $sql = "UPDATE `{$this->table}` SET {$set}";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        $allBindings = array_merge($updateBindings, $this->bindings);
        $this->db->prepareQuery($sql, $allBindings);

        return $this->db->getAffectedRows();
    }

    /**
     * Delete a record from the database.
     *
     * @return int The number of affected rows.
     */
    public function delete()
    {
        $sql = "DELETE FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        $this->db->prepareQuery($sql, $this->bindings);
        return $this->db->getAffectedRows();
    }
}