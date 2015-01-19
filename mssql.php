<?php

class Metrodb_Mssql extends Metrodb_Connector {

	public $RESULT_TYPE = MYSQL_ASSOC;
	public $persistent = 'n';
	public $isSelected = false;
	protected $_start  = -1;
	protected $_limit  = -1;
	public $type = 'mssql';
	public $escape = '"';

	/**
	 * Connect to the DB server
	 *
	 * Uses the classes internal host,user,password, and database variables
	 * @return void
	 */
	public function connect($options) {
		if (! function_exists('mssql_connect')) {
			return false;
		}
		if ($this->driverId == 0 ) {
			if ($this->persistent == 'y') {
				$this->driverId = @mssql_pconnect($this->host, $this->user, $this->password, TRUE);
			} else {
				$this->driverId = @mssql_connect($this->host, $this->user, $this->password, TRUE);
			}
			if (!$this->driverId) {
				throw new Exception("Unable to connect to database");
			}
		}
		if ($this->driverId) {
			$this->exec('SET QUOTED_IDENTIFIER ON');
			if (mssql_select_db($this->database, $this->driverId) ) {
				// __TODO__ perhaps we should throw an error and eat it up somewhere else?
				$this->isSelected = true;
			}
			$this->setOptions($options);
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

		$resSet = @mssql_query($queryString, $this->driverId);
		$this->row = 0;

		if (!$resSet ) {
			$this->getLastError();
			$l = _getMeA('logger');
			$l->err( sprintf("DB: %s (%d) %s. \nSQL: %s",$this->errorMessage, __LINE__, __FILE__, $queryString));

			return false;
		}
		if (is_resource($resSet) ) {
			$this->resultSet[] = $resSet;
		}
		if ($this->_start != -1 ) {
			if (!$this->seek($this->_start)) {
			}
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
		return mssql_query($statementString, $this->driverId);
	}


	/**
	 * Close connection
	 *
	 * @return void
	 */
	function close() {
		if ( is_resource($this->driverId) ) {
			mssql_close($this->driverId);
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

		if ($this->_limit > 1 && ($this->row >= ( $this->_start + $this->_limit))) {
			if (is_resource($this->resultSet[$resID]) ) {
				$this->freeResult($resID);
			}
			$this->_start = $this->_limit = -1;
			return false;
		}
		$this->record = mssql_fetch_array($this->resultSet[$resID], $this->RESULT_TYPE);
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
			mssql_free_result($resId);
		} else {
			mssql_free_result($this->resultSet[$resId]);
			unset($this->resultSet[$resId]);
			//reindex the keys
			$this->resultSet = array_merge($this->resultSet);
		}
		$this->_start = $this->_limit = -1;
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

	function queryGetOne($sql) {
		$this->query($sql);
		$this->nextRecord();
		$this->freeResult();
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
	 * Halt execution after a fatal DB error
	 *
	 * Called when the last query to the DB produced an error.
	 * Exiting from the program ensures that no data can be
	 * corrupted.  This is called only after fatal DB queries
	 * such as 'no such table' or 'syntax error'.
	 *
	 * @return void
	 */
	function halt() {
		print "We are having temporary difficulties transmitting to our database.  We recommend you stop for a few minutes, and start over again from the beginning of the website.  Thank you for your patience.";
		printf("<b>Database Error</b>: (%s) %s<br>%s\n", $this->errorNumber, $this->errorMessage, $this->queryString);
		exit();
	}



	/**
	 * Moves resultSet cursor to beginning
	 * @return void
	 */
	function reset($resID=0) {
		if (! $resID ) {
			$resID = count($this->resultSet) -1;
		}
		if (! isset($this->resultSet[$resID]) ) {
			return false;
		}

		return mssql_data_seek($this->Query_ID, 0);
	}


	/**
	 * Moves resultSet cursor to an aribtrary position
	 *
	 * @param int $row Desired index offset
	 * @return void
	 */
	function seek($row, $resID=0) {
		if (! $resID ) {
			$resID = count($this->resultSet) -1;
		}
		if (! isset($this->resultSet[$resID]) ) {
			return FALSE;
		}
		$x = @mssql_data_seek($this->resultSet[$resID], $row);
		if ($x) {
			$this->row = $row;
			return TRUE;
		}
		return FALSE;
	}


	/**
	 * Retrieves last error message from the DB
	 *
	 * @return string Error message
	 */
	function getLastError() {
		$this->errorMessage = @mssql_get_last_message();

		$row = $this->queryGetOne('SELECT @@ERROR as ErrorCode');
		$this->errorNumber = $row['ErrorCode'];
		return $this->errorMessage;
	}


	/**
	 * Return the last identity field to be created
	 *
	 * @return mixed
	 */
	function getInsertID() {
		$row =  $this->queryGetOne('SELECT SCOPE_IDENTITY()');
		return @$row['computed'];
	}


	/**
	 * Return the number of rows affected by the last query
	 *
	 * @return int number of affected rows
	 */
	function getNumRows() {
		$resID = count($this->resultSet) -1;
		return @mssqlnum_rows($this->resultSet[$resID]);
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
		//mssqllist_fields is deprecated, by more powerful than show columns
		#$dbfields = mssqllist_fields($this->database, $table, $this->driverId);
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

	function setLimit($s, $l) {
		$this->_start = $s;
		$this->_limit = $l;
		return '';
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
		return str_replace('\'', '\'\'', $val);
	}


}
