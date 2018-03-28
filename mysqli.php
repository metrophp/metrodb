<?php

class Metrodb_Mysqli extends Metrodb_Connector {

	public $RESULT_TYPE = MYSQLI_ASSOC;
	public $persistent  = 'n';
	public $isSelected  = false;
	public $port        = 3306;
	public $qc          = '`';
	public $collation   = 'COLLATE utf8_general_ci';
	public $tableOpts   = 'ENGINE=INNODB';

	/**
	 * Connect to the DB server
	 *
	 * Uses the classes internal host,user,password, and database variables
	 * @return void
	 */
	public function connect($options=array()) {
		if (! function_exists('mysqli_connect')) {
			if ($this->log) {
				$this->log->emerg("mysqli extension not available.");
			}

			return false;
		}
		if (!is_resource($this->driverId)) {
			if ($this->persistent == 'y') {
				$this->driverId = mysqli_connect('p:'.$this->host, $this->user, $this->password, '', $this->port);
			} else {
				$this->driverId = mysqli_connect($this->host, $this->user, $this->password, '', $this->port);
			}
			if (!$this->driverId) {
				if ($this->log) {
					$this->log->alert("Unable to connect to database: ".$this->database);
				}

				throw new Exception("Unable to connect to database");
			}
		}
		if ($this->driverId) {
			if (mysqli_select_db($this->driverId, $this->database) ) {
				// __TODO__ perhaps we should throw an error and eat it up somewhere else?
				$this->isSelected = true;
			}

			$this->setOptions($options);
		}
	}

	public function setAnsiQuotes() {
		$this->exec('SET SQL_MODE = \'ANSI_QUOTES\'');
		$this->qc = '"';
	}

	public function setAutocommitOff() {
		$this->exec("SET autocommit=0");
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
		$start = microtime(1);
		if (is_resource($this->driverId) ) {
			$this->connect();
		}
		//don't try to do queries if there's no DB
		if (! $this->isSelected ) {
			$this->errorMessage = 'no schema selected.';
			return false;
		}

		$resSet = mysqli_query($this->driverId, $queryString);

		$this->row = 0;

		if (!$resSet ) {
			$this->errorNumber = mysqli_errno($this->driverId);
			$this->errorMessage = mysqli_error($this->driverId);
			if ($this->log) {
				$this->log->error("Query failed ".$this->errorMessage, array('sql'=>$queryString));
			}
			return false;
		}
		if (is_object($resSet) ) {
			$this->resultSet[] = $resSet;
			//TODO: sometimes use mysqli_use_result
			mysqli_store_result($this->driverId);
		}

		$end = microtime(1);
		if ($this->log) {
			$this->log->debug("Query executed in: ".(sprintf("%0.4f", ($end - $start)*1000))." ms.", array('sql'=>$queryString));
		}
		return true;
	}


	function exec($statementString, $bind=NULL) {
		if (!is_resource($this->driverId)) {
			$this->connect();
		}

		$start = microtime(1);

		$stmt = mysqli_prepare($this->driverId, $statementString);
		if (!$stmt) {
			return FALSE;
		}
		if (is_array($bind)) {
			$args = array('');
			foreach ($bind as $idx=>$_b) {
				if (is_double($_b)) {
					$args[0] .= 'd';
				} else if (is_int($_b)) {
					$args[0] .= 'i';
				} else {
					$args[0] .= 's';
				}
				$args[] = &$bind[$idx];
			}
			call_user_func_array(array($stmt, 'bind_param'), $args);
		}

		$x =  mysqli_stmt_execute($stmt);
		$end = microtime(1);
		if ($this->log) {
			$this->log->debug("Statement executed in: ".(sprintf("%0.4f", ($end - $start)*1000))." ms.", array('sql'=>$statementString));
		}
		return $x;
//		return mysqli_query($this->driverId, $statementString);
	}


	/**
	 * Close connection
	 *
	 * @return void
	 */
	function close() {
		if ( is_resource($this->driverId) ) {
			mysqli_close($this->driverId);
		}
		$this->isSelected = false;
		$this->resultSet  = array();
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

		$this->record = mysqli_fetch_array($this->resultSet[$resID], $this->RESULT_TYPE);
		$this->row += 1;

		//no more records in the result set?
		$ret = is_array($this->record);
		if (! $ret ) {
			if (is_object($this->resultSet[$resID]) ) {
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
			mysqli_free_result($resId);
		} else {
			mysqli_free_result($this->resultSet[$resId]);
			unset($this->resultSet[$resId]);
			//reindex the keys
			$this->resultSet = array_merge($this->resultSet);
		}
	}

	/**
	 * Moves resultSet cursor to beginning
	 * @return void
	 */
	function reset($resId='') {
		if (! $resId ) {
			$resId = count($this->resultSet) -1;
		}
		mysqli_data_seek($this->resultSet[$resId], 0);
	}

	/**
	 * Moves resultSet cursor to an aribtrary position
	 *
	 * @param int $row Desired index offset
	 * @return void
	 */
	function seek($row, $resId='') {
		if (! $resId ) {
			$resId = count($this->resultSet) -1;
		}

		mysqli_data_seek($this->resultSet[$resId], $row);
		$this->row = $row;
	}


	/**
	 * Retrieves last error message from the DB
	 *
	 * @return string Error message
	 */
	function getLastError() {
		$this->errorNumber = mysqli_errno($this->driverId);
		$this->errorMessage = mysqli_error($this->driverId);
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
	public function getInsertID() {
		return mysqli_insert_id($this->driverId);
	}

	/**
	 * Return the number of rows affected by the last query
	 *
	 * @return int number of affected rows
	 */
	public function getNumRows() {
		$resID = count($this->resultSet) -1;
		return @mysqli_num_rows($this->resultSet[$resID]);
	}

	public function setType($type='ASSOC') {
		$this->prevType = $this->RESULT_TYPE;
		if ($type=='ASSOC') {
			$this->RESULT_TYPE = MYSQLI_ASSOC;
		}
		if ($type=='NUM') {
			$this->RESULT_TYPE = MYSQLI_NUM;
		}
		if ($type=='BOTH') {
			$this->RESULT_TYPE = MYSQLI_BOTH;
		}
	}

	public function quote($val) {
		return mysqli_real_escape_string($this->driverId, $val);
	}

	public function escapeCharValue($val) {
		return "'".addslashes(
			$val
		)."'";
	}

	public function escapeBinaryValue($val) {
		return '_binary\''.addcslashes(
			$val,
			"\x00\'\"\r\n"
		).'\'';
	}
}
