<?php declare(strict_types=1);

namespace CORE;

use DATABASE\DatabaseDriverInterface;

class QueryBuilder
{
    /**
     * The table which the query is targeting.
     */
    protected string $table;

    /**
     * The columns that should be returned.
     * @var array<int, string>
     */
    public array $columns = ['*'];

    /**
     * The where constraints for the query.
     * @var array<int, string>
     */
    public array $wheres = [];

    /**
     * The parameters for the query bindings.
     * @var array<int, mixed>
     */
    public array $bindings = [];

    public function __construct(
        protected DatabaseDriverInterface $db
    ) {}

    /**
     * Set the table for the query.
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Add a basic where clause to the query.
     */
    public function where(string $column, string $operator, mixed $value): self
    {
        $this->wheres[] = "`{$column}` {$operator} ?";
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Execute a query for a single record by ID.
     */
    public function find(int $id): ?array
    {
        return $this->where('id', '=', $id)->first();
    }

    /**
     * Execute the query and get the first result.
     */
    public function first(): ?array
    {
        $results = $this->get();
        return count($results) > 0 ? $results[0] : null;
    }

    /**
     * Execute the query as a "select" statement.
     */
    public function get(): array
    {
        $sql = "SELECT " . implode(', ', $this->columns) . " FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        $result = $this->db->prepareQuery($sql, $this->bindings);

        $data = [];
        // PDO returns a PDOStatement, mysqli returns mysqli_result
        if ($result instanceof \PDOStatement) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        } elseif ($result instanceof \mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }

    /**
     * Set the columns to be selected.
     */
    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Insert a new record into the database.
     */
    public function insert(array $values): bool
    {
        $columns = '`' . implode('`, `', array_keys($values)) . '`';
        $placeholders = rtrim(str_repeat('?, ', count($values)), ', ');

        $sql = "INSERT INTO `{$this->table}` ({$columns}) VALUES ({$placeholders})";

        $result = $this->db->prepareQuery($sql, array_values($values));
        return $result !== false;
    }

    /**
     * Update a record in the database.
     */
    public function update(array $values): int
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
     */
    public function delete(): int
    {
        $sql = "DELETE FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        $this->db->prepareQuery($sql, $this->bindings);
        return $this->db->getAffectedRows();
    }
}