<?php
namespace DATABASE\MYSQL;

use \BOOT\Log;

class Transaction
{
	public $db				= NULL;
	public $transaction 	= FALSE;
    public $level           = 0;

	/**
	 * Is Master?
	 * @return boolean
	 */
	final private function __isMaster()
	{
		if( ! $this->db->isMaster)
		{
			throw new \Exception('Is not a master.', 420);
		}
	}

	/**
	 * Is to use transaction?
	 * @return boolean
	 */
	final private function __isTransaction()
	{
		if( ! $this->transaction )
		{
			throw new \Exception('Do not using transaction.', 421);
		}
	}

	/**
	 * Run transaction
	 */
	final public function run(\Closure $callback)
	{
		try
		{
			$this->start();

			$result = $callback();

			$this->commit();

			return $result;
		}
		catch (\Exception $e)
		{
			$this->rollback();
			throw new \BOOT\Exception($e->getMessage());
		}
	}

	/**
	 * Start transaction
	 */
	final public function start()
	{
		try
		{
			$this->__isMaster();

            if($this->level == 0)
            {
	            $this->db->connector->query('SET AUTOCOMMIT=0');
	            $this->db->connector->query('START TRANSACTION'); // can also be BEGIN or BEGIN WORK
	            $this->transaction = TRUE;

	            Log::w('INFO', 'Start Transaction');
            }

            $this->db->connector->query('SAVEPOINT s' . ++$this->level);
		}
		catch (\Exception $e)
		{
			throw new \Exception('Transaction::start Exception', 422);
		}
	}

	/**
	 * End transaction
	 */
	final public function end()
	{
		try
		{
			$this->__isMaster();
			$this->db->connector->query('SET AUTOCOMMIT=1');
			$this->transaction = FALSE;

			Log::w('INFO', 'End Transaction');
		}
		catch (\Exception $e)
		{
			throw new \Exception('Transaction::end Exception', 423);
		}
	}

	/**
	 * Commit
	 */
	final public function commit()
	{
		try
		{
			$this->__isMaster();
			$this->__isTransaction();

			$this->db->connector->query('RELEASE SAVEPOINT s' . $this->level);

			$this->level--;

			if($this->level == 0)
			{
				$this->db->connector->query('COMMIT');
				$this->end();

				Log::w('INFO', 'Commit');
			}
		}
		catch (\Exception $e)
		{
			throw new \Exception('Transaction::commit Exception', 424);
		}
	}

	/**
	 * Rollback
	 */
	final public function rollback()
	{
		try
		{
			$this->__isMaster();
			$this->__isTransaction();

			$this->db->connector->query('ROLLBACK TO SAVEPOINT s' . $this->level);
			$this->db->connector->query('RELEASE SAVEPOINT s' . $this->level);

			$this->level--;

			if($this->level == 0)
			{
				$this->db->connector->query('ROLLBACK');
				$this->end();

				Log::w('INFO', 'Rollback');
			}
		}
		catch (\Exception $e)
		{
			throw new \Exception('Transaction::rollback Exception' . $e->getMessage(), 425);
		}
	}
}