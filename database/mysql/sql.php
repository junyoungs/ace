<?php
namespace DATABASE\MYSQL;

use \BOOT\Log;

class Sql
{
	public $db				= NULL;

	/**
	 * Execute a prepared statement query. This is the new, secure method.
	 *
	 * @param string $sql SQL query with placeholders (?)
	 * @param array  $params Parameters to bind
	 * @return \mysqli_result|bool
	 */
	final public function query($sql, $params = [])
	{
		return $this->db->connector->prepareQuery($sql, $params);
	}

	/**
	 * Excute sql
	 * @param String $sql
	 * @deprecated This method is vulnerable to SQL injection. Use query() with prepared statements instead.
	 */
	final public function execute($sql)
	{
		throw new \Exception("execute() is deprecated due to security risks. Use query() with prepared statements.");
	}

	/**
	 * Escape
	 * @param String $sql
	 */
	final public function escape($v)
	{
		return $this->db->connector->escape( $v );
	}


	/**
	 * Secure Sql
	 * @param unknown $sql
	 * @return unknown
	 */
	final public function secureSql($sql)
	{
		return $sql;

		//throw new \Exception('Sql:secureSql Exception', 440);
	}


	/**
	 *
	 * @param unknown $data
	 * @throws \Exception
	 */
	public function insertString($data, $none_escape = array())
	{
		$data = $this->checkData( $data, $none_escape );

		if( count($data) > 0 )
		{
			return '('.implode(',', array_keys($data)).') VALUES ('.implode(',', array_values($data)).')';
		}
		throw new \Exception('Sql::insertString Exception');
	}



	public function updateString($data, $none_escape = array())
	{
		$data = $this->checkData( $data, $none_escape );

		if( count($data) > 0 )
		{
			$sql = array();
			foreach($data as $k => $v)
			{
				$sql[] = $k . ' = ' . $v;

			}
			return implode(',', $sql);
		}
		throw new \Exception('Sql::updateString Exception');

	}


































	/**
	 * Select Count(*) AS cnt From Table Where....
	 * @param unknown $table
	 * @param unknown $where
	 * @return string
	 */
	public function c($table, $where=array())
	{
		return 'SELECT COUNT(*) AS cnt FROM '.$this->checkTable($table).' WHERE ' . $this->where( $where );
	}

	/**
	 * Select Count(*) AS cnt From Table
	 * @param Array $data
	 * @throws \Exception
	 * @return String
	 */
	public function ca($table)
	{
		return 'SELECT COUNT(*) AS cnt FROM '.$this->checkTable($table);
	}


	/**
	 * Insert
	 * @param Array $data
	 * @throws \Exception
	 * @return String
	 */
	public function i($table, $data=array(), $none_escape = array() )
	{
		return 'INSERT INTO '.$this->checkTable($table).' '.$this->insertString( $data, $none_escape );
	}


	/**
	 * Update
	 * @param Array $data
	 * @param Array $where
	 * @throws \Exception
	 * @return String
	 */
	public function u($table, $data=array(), $where=array(), $none_escape = array())
	{
		return 'UPDATE '.$this->checkTable($table).' SET '.$this->updateString( $data, $none_escape ) . ' WHERE ' . $this->where( $where );
	}


	/**
	 * Delete
	 * @param string $table
	 * @param string $field
	 * @param array $where
	 * @return string
	 */
	public function d($table, $field, $where=array())
	{
		return $this->u( $table, array( $field => 'T' ), $where );
	}


	/**
	 * Physical Delete
	 * @param string $table
	 * @param array $where
	 * @return string
	 */
	public function dp($table, $where = array())
	{
		return 'DELETE FROM '.$this->checkTable($table).' WHERE '.$this->where($where);
	}




























	/**
	 * Check a field
	 * @param String $t
	 * @throws \Exception
	 * @return string
	 */
	public function checkTable($t)
	{
		$t = $this->escape( strtolower( trim( str_replace(array('`', '\'', '"'), '', (string)$t ) ) ) );
		if(strlen($t) > 0)
		{
			return $t;
		}

		throw new \Exception('Sql::checkTable Exception', 441);
	}


	/**
	 * Check a field
	 * @param String $f
	 * @throws \Exception
	 * @return string
	 */
	public function checkField($f)
	{
		$f = $this->escape( strtolower( trim( str_replace(array('`', '\'', '"'), '', (string)$f ) ) ) );
		if(strlen($f) > 0)
		{
			return $f;
		}

		throw new \Exception('Sql::checkField Exception', 442);
	}

	/**
	 * Check a value
	 * @param unknown $v
	 * @throws \Exception
	 * @return string
	 */
	public function checkValue($v)
	{
		if( is_string($v) )
		{
			return "'".$this->escape( trim( (string)$v ) )."'";

		}
		elseif( is_float($v) )
		{
			return $this->escape( (float)$v );

		}
		elseif( is_int($v) )
		{
			return $this->escape( (int)$v );
		}
		elseif( is_null($v) )
		{
			return $this->escape( NULL );
		}
		elseif( is_bool($v) )
		{
			return $this->escape( $v ? TRUE : FALSE );
		}

		throw new \Exception('Sql::checkValue Exception', 443);
	}



	/**
	 * check data
	 * @param Array $data
	 * @throws \Exception
	 * @return Array
	 */
	public function checkData($data = array(), $none_escape = array())
	{
		$data = (array)$data;
		if( count($data) > 0 )
		{
			$tmp = array();
			foreach( $data as $k => $v )
			{
				if(!in_array($k, $none_escape))
				{
					$v = $this->checkValue($v);
				}
				$k = str_replace('`', '', $this->checkField($k));
				$tmp[$k] = $v;
			}
			unset($data);

			if(count($tmp) > 0)
			{
				return $tmp;
			}
		}
		throw new \Exception('Sql::checkData Exception', 444);
	}


	/**
	 * make where
	 * @param Array $where
	 * @throws \Exception
	 * @return string
	 */
	public function where($where = array())
	{
		$where = (array)$where;
		if(count($where) > 0)
		{
			foreach($where as $k => $v)
			{
				$where[$k] = trim($v);
				if(strlen($where[$k]) < 3)
				{
					unset($where[$k]);
				}
			}
			return implode(' AND ', $where);
		}
		throw new \Exception('Sql:where Exception', 445);
	}


}