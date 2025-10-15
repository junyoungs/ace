<?php

namespace ACE;

use \PDO;

class SqliteConnector implements \ACE\Database\DatabaseDriverInterface
{
    /**
     * @var PDO|null
     */
    public $conn = null;

    /**
     * @var \PDOStatement|null
     */
    private $lastStatement = null;

    public function connect(array $config)
    {
        if (is_null($this->conn)) {
            if (empty($config['path'])) {
                throw new \Exception("SQLite path not specified in config.");
            }
            // Ensure the directory exists
            $dbDir = dirname($config['path']);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            try {
                $this->conn = new PDO("sqlite:" . $config['path']);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                Log::w('INFO', 'Connected: Database > SQLite');
            } catch (\PDOException $e) {
                throw new \Exception('SQLite Connection Error: ' . $e->getMessage());
            }
        }
    }

    public function close()
    {
        if ($this->conn) {
            $this->conn = null;
            Log::w('INFO', 'Disconnected: Database > SQLite');
        }
    }

    public function query($sql)
    {
        $this->checkConnected();
        try {
            return $this->conn->query($sql);
        } catch (\PDOException $e) {
            throw new \Exception('SQLite Query Exception: ' . $e->getMessage() . ' > ' . $sql);
        }
    }

    public function prepareQuery($sql, $params = [])
    {
        $this->checkConnected();
        try {
            $this->lastStatement = $this->conn->prepare($sql);
            $this->lastStatement->execute($params);
            return $this->lastStatement;
        } catch (\PDOException $e) {
            throw new \Exception('SQLite Prepare/Execute Failed: ' . $e->getMessage());
        }
    }

    public function error()
    {
        if ($this->conn) {
            $errorInfo = $this->conn->errorInfo();
            return $errorInfo[2] ?? 'No error';
        }
        return 'No active connection.';
    }

    public function getAffectedRows()
    {
        if ($this->lastStatement) {
            return $this->lastStatement->rowCount();
        }
        return 0;
    }

    public function beginTransaction()
    {
        $this->checkConnected();
        $this->conn->beginTransaction();
    }

    public function commit()
    {
        $this->checkConnected();
        $this->conn->commit();
    }

    public function rollBack()
    {
        $this->checkConnected();
        $this->conn->rollBack();
    }

    private function checkConnected()
    {
        if (is_null($this->conn)) {
            throw new \Exception('Not connected to SQLite database.');
        }
    }
}