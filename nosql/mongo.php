<?php

include_once( dirname(__FILE__).'/../connector.php');
class Metrodb_Nosql_Mongo extends Metrodb_Connector {

	public $persistent = 'n';
	public $isSelected = false;

	/**
	 * Connect to the DB server
	 *
	 * Uses the classes internal host,user,password, and database variables
	 * @return void
	 */
	function connect($options) {
		if (! class_exists('mongo')) {
			return false;
		}
		$this->driverId = new Mongo('mongodb://'.$this->host);
		$this->db = $this->driverId->selectDB($this->database);
		$this->isSelected = true;
	}


	/**
	 * Send query to the DB
	 *
	 * Results are stored in $this->resultSet;
	 * @return  void
	 * @param  string $queryString SQL command to send
	 */
	function query($queryString, $log = true) {

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

		$resSet = mysql_query($queryString, $this->driverId);
		$this->row = 0;

		if (!$resSet ) {
			$this->errorNumber = mysql_errno();
			$this->errorMessage = mysql_error();
			if ($log) {
				trigger_error('database error: ('.$this->errorNumber.') '.$this->errorMessage.'
					<br/> statement was: <br/>
					'.$queryString);
			}
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


	function exec($statementString, $log = true) {
		if ($this->driverId == 0 ) {
			$this->connect();
		}
		//don't try to do queries if there's no DB
		if (! $this->isSelected ) {
			$this->errorMessage = 'no schema selected.';
			return false;
		}
		return mysql_query($statementString, $this->driverId);
	}


	/**
	 * Close connection
	 *
	 * @return void
	 */
	function close() {
		if ( is_resource($this->driverId) ) {
			$this->driverId->close();
		}
		$this->driverId = null;
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

	function queryGetOne($sql) {
		$this->query($sql);
		$this->nextRecord();
		$this->freeResult();
		return $this->record;
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
	function reset() {
	}


	/**
	 * Moves resultSet cursor to an aribtrary position
	 *
	 * @param int $row Desired index offset
	 * @return void
	 */
	function seek($row) {
	}


	/**
	 * Retrieves last error message from the DB
	 *
	 * @return string Error message
	 */
	function getLastError() {
		$this->errorMessage = $this->db->runCommand(array( 
			 'getlasterror' => 1, 
			 'w' => $slaves, 
			 'wtimeout' => 3000, 
			 )); 
		return $this->errorMessage;
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
