<?php

class Metrodb_Mysql extends Metrodb_Connector {

	public $RESULT_TYPE = MYSQL_ASSOC;
	public $persistent = 'n';
	public $isSelected = false;
	public $port       = 3306;
	public $qc = '"';
	public $collation = 'COLLATE utf8_general_ci';
	public $tableOtps = 'ENGINE=INNODB';

	/**
	 * Connect to the DB server
	 *
	 * Uses the classes internal host,user,password, and database variables
	 * @return void
	 */
	public function connect() {
		if (! function_exists('mysql_connect')) {
			return false;
		}
		if ($this->driverId == 0 ) {
			$host = $this->host;
			$host .= ':'.$this->port;
			if ($this->persistent == 'y') {
				$this->driverId = @mysql_pconnect($host, $this->user, $this->password, TRUE);
			} else {
				$this->driverId = @mysql_connect($host, $this->user, $this->password, TRUE);
			}
			if (!$this->driverId) {
				throw new Exception("Unable to connect to database");
			}
		}
		if ($this->driverId) {
			if (mysql_select_db($this->database, $this->driverId) ) {
				// __TODO__ perhaps we should throw an error and eat it up somewhere else?
				$this->isSelected = true;
			}
			$this->exec('SET SQL_MODE = \'ANSI_QUOTES\'');
		}
	}


	/**
	 * Send query to the DB
	 *
	 * Results are stored in $this->resultSet;
	 * @return  void
	 * @param  string $queryString SQL command to send
	 */
	public function query($queryString) {

		$this->queryString = $queryString;
		$start = microtime();
		if ($this->driverId == 0 ) {
			$this->connect();
		}
		//don't try to do queries if there's no DB
		if (! $this->isSelected ) {
			$this->errorMessage = 'no schema selected.';
			return false;
		}

		$resSet = @mysql_query($queryString, $this->driverId);
		$this->row = 0;

		if (!$resSet ) {
			$this->errorNumber = mysql_errno();
			$this->errorMessage = mysql_error();
			return false;
		}
		if (is_resource($resSet) ) {
			$this->resultSet[] = $resSet;
		}
		return true;

		/*
		$end = microtime();
		$j = split(" ", $start);
		$s = $j[1] = $j[0];
		$f = split(" ", $end);
		$e = $f[1] = $f[0];
		if (($e-$s) >.1) {
			// slow query
		}
		$this->exectime = abs($e-$s);
		$this->log();
		 */
	}


	function exec($statementString) {
		if ($this->driverId == 0 ) {
			$this->connect();
		}
		//sometimes we need to create a new DB (schema)
		// !$this->isSelected is not always an error condition

		//don't try to do queries if there's no DB
		/*
		if (! $this->isSelected ) {
			$this->errorMessage = 'no schema selected.';
			return false;
		}
		 */
		return mysql_query($statementString, $this->driverId);
	}


	/**
	 * Close connection
	 *
	 * @return void
	 */
	function close() {
		if ( is_resource($this->driverId) ) {
			mysql_close($this->driverId);
		}
		$this->driverId = 0;
	}


	/**
	 * Grab the next record from the resultSet
	 *
	 * Returns true while there are more records, false when the set is empty
	 * Automatically frees result when the result set is emtpy
	 * @return boolean
	 * @param  int $resID Specific resultSet, default is last query
	 */
	function nextRecord($resID = false) {
		if (! $resID ) {
			$resID = count($this->resultSet) -1;
		}
		if (! isset($this->resultSet[$resID]) ) {
			return false;
		}

		$this->record = mysql_fetch_array($this->resultSet[$resID], $this->RESULT_TYPE);
		$this->row += 1;

		//no more records in the result set?
		$ret = is_array($this->record);
		if (! $ret ) {
			if (is_resource($this->resultSet[$resID]) ) {
				$this->freeResult($resID);
			}
		}
		return $ret;
	}


	/**
	 * Pop the top result off the stack
	 */
	function freeResult($resId = FALSE) {
		if (! $resId ) {
			$resId = array_pop($this->resultSet);
			mysql_free_result($resId);
		} else {
			mysql_free_result($this->resultSet[$resId]);
			unset($this->resultSet[$resId]);
			//reindex the keys
			$this->resultSet = array_merge($this->resultSet);
		}
	}


	/**
	 * Short hand for query() and nextRecord().
	 *
	 * @param string $sql SQL Command
	 */
	function queryOne($sql) {
		$this->query($sql);
		$this->nextRecord();
		$this->freeResult();
	}


	/**
	 * Short hand for query() and nextRecord().
	 *
	 * @param string $sql SQL Command
	 * @return array Record
	 */
	function queryGetOne($sql) {
		$this->queryOne($sql);
		return $this->record;
	}


	/**
	 * Short hand way to send a select statement.
	 *
	 * @param string $table  SQL table name
	 * @param string $fields  Column names
	 * @param string $where  Additional where clause
	 * @param string $orderby Optional orderby clause
	 */
	function select($table, $fields = "*", $where = "", $orderby = "") {
		if ($where) {
			$where = " where $where";
		}
		if ($orderby) {
			$orderby = " order by $orderby";
		}
		$sql = "select $fields from $table $where $orderby";
		$this->query($sql);
	}


	/**
	 * Short hand way to send a select statement and pull back one result.
	 *
	 * @param string $table  SQL table name
	 * @param string $fields  Column names
	 * @param string $where  Additional where clause
	 * @param string $orderby Optional orderby clause
	 */
	function selectOne($table, $fields = "*", $where = "", $orderby = "") {
		if ($where) {
			$where = " where $where";
		}
		if ($orderby) {
			$orderby = " order by $orderby";
		}
		$sql = "select $fields from $table $where $orderby";
		$this->queryOne($sql);
	}


	/**
	 * Moves resultSet cursor to beginning
	 * @return void
	 */
	function reset() {
		mysql_data_seek($this->Query_ID, 0);
	}


	/**
	 * Moves resultSet cursor to an aribtrary position
	 *
	 * @param int $row Desired index offset
	 * @return void
	 */
	function seek($row) {
		mysql_data_seek($this->resultSet, $row);
		$this->row = $row;
	}


	/**
	 * Retrieves last error message from the DB
	 *
	 * @return string Error message
	 */
	function getLastError() {
		$this->errorNumber = mysql_errno();
		$this->errorMessage = mysql_error();
		return $this->errorMessage;
	}

	public function rollbackTx() {
		$this->exec("ROLLBACK");
		$this->exec("SET autocommit=1");
	}

	public function startTx() {
		$this->exec("SET autocommit=0");
		$this->exec("START TRANSACTION");
	}

	public function commitTx() {
		$this->exec("COMMIT TRANSACTION");
		$this->exec("SET autocommit=1");
	}

	/**
	 * Return the last identity field to be created
	 *
	 * @return mixed
	 */
	function getInsertID() {
		return mysql_insert_id($this->driverId);
	}


	/**
	 * Return the number of rows affected by the last query
	 *
	 * @return int number of affected rows
	 */
	function getNumRows() {
		$resID = count($this->resultSet) -1;
		return @mysql_num_rows($this->resultSet[$resID]);
	}


	function getTables() {
		$this->query("show tables");
		$j = $this->RESULT_TYPE;
		$this->RESULT_TYPE = MYSQL_BOTH;
		while ($this->nextRecord()) {
			$x[] = $this->record[0];
		}
		$this->RESULT_TYPE = $j;
		return $x;
	}

	function getTableIndexes($table = '') {
		$this->query("show index from `$table`");
		while ($this->nextRecord()) {
			extract($this->record);
			$_idx[$Key_name][$Seq_in_index]['column'] = $Column_name;
			$_idx[$Key_name][$Seq_in_index]['unique'] = $Non_unique;
		}
		return $_idx;
	}


	/**
	 * Return column definitions in array format
	 *
	 * @return Array   list of structures that define a table's columns.
	 */
	function getTableColumns($table = '') {
		if ($this->driverId == 0 ) {
			$this->connect();
		}
		$dbfields = $this->queryGetAll("show columns from `$table`", FALSE);
		//mysql_list_fields is deprecated, by more powerful than show columns
		#$dbfields = mysql_list_fields($this->database, $table, $this->driverId);
		if (!$dbfields) {
			return false;
		}
		$returnFields = array();
		foreach($dbfields as $_st) {
			$name = $_st['Field'];
			$type = $_st['Type'];
			$size = '';
			if (strpos($type, '(') !== FALSE) {
				$size = substr($type, strpos($type, '(')+1,  (strpos($type, ')') -strpos($type, '(')-1) );
				$type = substr($type, 0, strpos($type, '('));
			}
			$def = $_st['Default'];
			$flags = '';
			if ($_st['Null'] == 'NO') {
				$null = 'NOT NULL';
				$flags .= 'not_null ';
			} else {
				$null = 'NULL';
				$flags .= 'null ';
			}
			if (stripos($_st['Type'], 'unsigned') !== FALSE) {
				$flags .= 'unsigned ';
			}
			if (stripos($_st['Extra'], 'auto_increment') !== FALSE) {
				$flags .= 'auto_increment ';
			}

			$returnFields[] = array(
				'name'=>  $name,
				'type'=>  $type,
				'len' =>  $size,
				'flags'=> $flags,
				'def'  => $def,
				'null' => $null);

				/*
			$field['name'][$name] = $name;
			$field['type'][$name] = $type;
			$field['len'][$name]  = $size;
			$field['flags'][$name] = $flags;
			$field['def'][$name] = $def;
			$field['null'][$name] = $null;
				 */
		}
		return $returnFields;
		//return $field;
	}

	function setType($type='ASSOC') {
		$this->prevType = $this->RESULT_TYPE;
		if ($type=='ASSOC') {
			$this->RESULT_TYPE = MYSQL_ASSOC;
		}
		if ($type=='NUM') {
			$this->RESULT_TYPE = MYSQL_NUM;
		}
		if ($type=='BOTH') {
			$this->RESULT_TYPE = MYSQL_BOTH;
		}
	}

	function quote($val) {
		return mysql_real_escape_string($val);
	}
}
