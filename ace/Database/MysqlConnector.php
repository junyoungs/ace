<?php
namespace ACE\Database;

class MysqlConnector implements \ACE\Database\DatabaseDriverInterface
{
	public $conn = NULL;

	/**
	 * Connect to the database.
	 *
	 * @param array $config
	 * @throws \Exception
	 */
	public function connect(array $config)
	{
		if (is_null($this->conn)) {
            if (isset($config['port'])) {
                $this->conn = mysqli_connect($config['host'], $config['user'], $config['password'], $config['database'], $config['port']);
            } else {
                $this->conn = mysqli_connect($config['host'], $config['user'], $config['password'], $config['database']);
            }

            if (!$this->conn) {
                throw new \Exception('MySQL Connection Error: ' . mysqli_connect_error());
            }

            $this->query("set names utf8");
        }
	}

	/**
	 * Close the database connection.
	 */
	public function close()
	{
		if ($this->conn) {
			$this->conn->close();
			$this->conn = NULL;
		}
	}

	/**
	 * Execute a raw query.
	 * @deprecated Prefer prepareQuery for security.
	 */
	public function query($sql)
	{
		$this->checkConnected();
		if ($query = $this->conn->query($sql)) {
			return $query;
		}
		throw new \Exception('MySQL Query Exception: [' . $this->conn->errno . '] ' . $this->conn->error . ' > ' . $sql);
	}

	/**
	 * Execute a prepared statement.
	 */
	public function prepareQuery($sql, $params = [])
	{
		$this->checkConnected();
		$stmt = $this->conn->prepare($sql);

		if ($stmt === false) {
			throw new \Exception('MySQL Prepare Failed: (' . $this->conn->errno . ') ' . $this->conn->error . ' > ' . $sql);
		}

		if (!empty($params)) {
			$types = str_repeat('s', count($params));
			$stmt->bind_param($types, ...$params);
		}

		if (!$stmt->execute()) {
			throw new \Exception('MySQL Execute Failed: (' . $stmt->errno . ') ' . $stmt->error);
		}

		$result = $stmt->get_result();
		$stmt->close();
		return $result;
	}

	/**
	 * Get the last database error.
	 */
	public function error()
	{
		if ($this->conn) {
			return $this->conn->error;
		}
		return 'No active connection.';
	}

    /**
     * Check if the connection is established.
     * @throws \Exception
     */
    private function checkConnected()
    {
        if (is_null($this->conn)) {
            throw new \Exception('Not connected to MySQL database.');
        }
    }

    /**
     * Get the number of affected rows.
     */
    public function getAffectedRows()
    {
        if ($this->conn) {
            return $this->conn->affected_rows;
        }
        return 0;
    }

    public function beginTransaction()
    {
        $this->checkConnected();
        $this->conn->begin_transaction();
    }

    public function commit()
    {
        $this->checkConnected();
        $this->conn->commit();
    }

    public function rollBack()
    {
        $this->checkConnected();
        $this->conn->rollback();
    }

    public function getLastInsertId(): int
    {
        if ($this->conn) {
            return $this->conn->insert_id;
        }
        return 0;
    }
}