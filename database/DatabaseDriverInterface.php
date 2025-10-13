<?php

namespace DATABASE;

interface DatabaseDriverInterface
{
    /**
     * Establish a database connection.
     *
     * @param array $config
     * @return void
     */
    public function connect(array $config);

    /**
     * Close the database connection.
     *
     * @return void
     */
    public function close();

    /**
     * Execute a raw SQL query.
     *
     * @param string $sql
     * @return mixed
     * @deprecated Prefer prepareQuery for security.
     */
    public function query($sql);

    /**
     * Execute a prepared statement with bound parameters.
     *
     * @param string $sql
     * @param array $params
     * @return mixed
     */
    public function prepareQuery($sql, $params = []);

    /**
     * Get the last error message.
     *
     * @return string
     */
    public function error();

    /**
     * Get the number of affected rows from the last operation.
     *
     * @return int
     */
    public function getAffectedRows();

    /**
     * Begin a new database transaction.
     */
    public function beginTransaction();

    /**
     * Commit the active database transaction.
     */
    public function commit();

    /**
     * Roll back the active database transaction.
     */
    public function rollBack();
}