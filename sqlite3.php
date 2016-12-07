<?php

class Metrodb_Sqlite3 extends Metrodb_Connector {

	public $resultType  = SQLITE3_ASSOC;
	public $isSelected  = false;
	public $qc          = '"';
	public $collation   = '';


	/**
	 * Connect to the DB server
	 *
	 * Uses the classes internal host,user,password, and database variables
	 * @return void
	 */
	public function connect($options=array()) {
		if (! class_exists('SQLite3')) {
			return false;
		}
		if ($this->driverId == 0 ) {
			$this->driverId = new SQLite3($this->database);
			if (!$this->driverId) {
				throw new Exception("Unable to connect to database");
			}
		}
		if ($this->driverId) {
			$this->isSelected = true;
			$this->setOptions($options);
		}
	}


	/*
	public function setAnsiQuotes() {
		$this->exec('SET SQL_MODE = \'ANSI_QUOTES\'');
		$this->qc = '"';
	}
	 */


	public function setTempStoreMemory() {
		$this->exec('PRAGMA temp_store = 2');
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
		if (! is_object($this->driverId) ) {
			$this->connect();
		}
		//don't try to do queries if there's no DB
		if (! $this->isSelected ) {
			$this->errorMessage = 'no schema selected.';
			return FALSE;
		}

		//docs say returns false on error, but actually throws Exception
		try {
			$resSet = @$this->driverId->query($queryString);
		} catch (Exception $e) {
			//echo $e->getMessage()."\n";
			//echo $queryString."\n";
			return FALSE;
		}
		$this->row = 0;

		if (is_object($resSet) ) {
			$this->resultSet[] = $resSet;
		} else {
			$this->getLastError();
		}

		$this->sqltime = abs(microtime(1)-$start);
		return is_object($resSet);
	}


	/**
	 * return SQLite3Stmnt object
	 *
	 * @param string $statementString SQL statement
	 * @param mixed $bind optional array of values for ? replacement or null
	 * @return SQLite3Stmt
	 */
	function _prepareStatement($statementString, $bind=array()) {
		$last = 0;
		$last = strrpos($statementString, '?', $last);
		$stmt = @$this->driverId->prepare($statementString);

		if (is_array($bind)) {
			foreach ($bind as $_k => $_v) {
				if (is_double($_v)) {
					$stmt->bindValue($_k, $_v, SQLITE_FLOAT);
				} else if( is_int($val) ) {
					$stmt->bindValue($_k, $_v, SQLITE_INTEGER);
				} else {
					$stmt->bindValue($_k, $_v, SQLITE_BLOB);
				}
			}
		}
		return $stmt;
	}

	/**
	 * Prepare statementString into a SQLite3Stmt and return
	 * result of $stmt->execute()
	 *
	 * @param string $statementString SQL statement
	 * @param mixed $bind optional array of values for ? replacement or null
	 * @return SQLite3Result
	 */
	function exec($statementString, $bind=NULL) {
		if (!is_object($this->driverId)) {
			$this->connect();
		}

		$stmt = $this->_prepareStatement($statementString, $bind);
		if (!$stmt) {
			return FALSE;
		}

		return $stmt->execute();
	}


	/**
	 * Close connection
	 *
	 * @return void
	 */
	function close() {
		if ( is_object($this->driverId) ) {
			$this->driverId->close();
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
	function nextRecord($resId = false) {
		if (! $resId ) {
			$resId = count($this->resultSet) -1;
		}
		if (! isset($this->resultSet[$resId]) ) {
			return false;
		}
		$resultObj = $this->resultSet[$resId];

		$this->record = $resultObj->fetchArray($this->resultType);
		$this->row += 1;

		//no more records in the result set?
		$ret = is_array($this->record);
		if (! $ret ) {
			if (is_resource($this->resultSet[$resId]) ) {
				$this->freeResult($resId);
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
			$resId->finalize();
		} else {
			$resObj = $this->resultSet[$resId];
			$resObj->finalize();
			unset($this->resultSet[$resId]);
			//reindex the keys
			$this->resultSet = array_merge($this->resultSet);
		}
	}


	/**
	 * Moves resultSet cursor to beginning
	 * @return void
	 */
	function reset($resId = FALSE) {
		if (! $resId ) {
			$resId = count($this->resultSet) -1;
		}
		$resObj = $this->resultSet[$resId];
		$resObj->reset();
		$this->row = 0;
	}

	/**
	 * Moves resultSet cursor to an aribtrary position
	 *
	 * @param int $row Desired index offset
	 * @return void
	 */
	function seek($row) {
		$this->reset();
		for ($x=0; $x < $row; $x++) {
			$this->nextRecord();
		}
	}

	/**
	 * Retrieves last error message from the DB
	 *
	 * @return string Error message
	 */
	function getLastError() {
		$this->errorNumber  = $this->driverId->lastErrorCode();
		$this->errorMessage = $this->driverId->lastErrorMsg();
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
		return $this->driverId->lastInsertRowID();
	}

	/**
	 * Not implemented for SQLite3
	 *
	 * @return int number of affected rows
	 */
	function getNumRows() {
		return 0;
	}

	public function truncate($tbl) {
		$qc = $this->qc;
		return $this->exec("DELETE FROM ".$qc.$tbl.$qc);
	}

	public function getTables() {
		$this->query("SELECT name FROM sqlite_master WHERE type = 'table'");
		$j = $this->RESULT_TYPE;
		$this->RESULT_TYPE = SQLITE3_BOTH;
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
		if (!is_object($this->driverId)) {
			$this->connect();
		}
		$dbfields = $this->queryGetAll("SELECT * from sqlite_master where type = 'table' AND name = '$table'");
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
			$this->RESULT_TYPE = SQLITE3_ASSOC;
		}
		if ($type=='NUM') {
			$this->RESULT_TYPE = SQLITE3_NUM;
		}
		if ($type=='BOTH') {
			$this->RESULT_TYPE = SQLITE3_BOTH;
		}
	}

	function quote($val) {
		return $this->driverId->escapeString($val);
	}

	public function dynamicCreateSql($dataitem) {
		$sql = "";
		//$props = $dataitem->__get_props();
		$finalTypes = array('created_on'=>'ts', 'updated_on'=>'ts');

		$vars = get_object_vars($dataitem);
		$keys = array_keys($vars);
		$fields = array();
		$values = array();
		foreach ($keys as $k) {
			if (substr($k,0,1) == '_') { continue; }
			//fix for SQLITE
			if (isset($dataitem->_pkey) && $k === $dataitem->_pkey && $vars[$k] == NULL ) {continue;}

			if (array_key_exists($k, $dataitem->_typeMap)) {
				$finalTypes[$k] = $dataitem->_typeMap[$k];
			} else {
				//only override created_on and update_on explicitly
				if (!isset($finalTypes[$k])) {
					$finalTypes[$k] = "string";
				}
			}
		}

		$tableName = $this->qc.$dataitem->_table.$this->qc;
		/**
		 * build SQL
		 */
		$sql = "CREATE TABLE IF NOT EXISTS ".$tableName." ( \n";

		if ($dataitem->_pkey !== NULL && ! array_key_exists($dataitem->_pkey, $finalTypes)) {
			$sqlDefs[$dataitem->_pkey] = $this->qc.$dataitem->_pkey.$this->qc." INTEGER PRIMARY KEY";
		}

		foreach($finalTypes as $propName=>$type) {
			$colName = $this->qc.$propName.$this->qc;
			switch($type) {
			case "email":
				$sqlDefs[$propName] = "$colName varchar(255)";
				break;
			case "ts":
				$sqlDefs[$propName] = "$colName int(11) NULL DEFAULT NULL";
				break;
			case "int":
				$sqlDefs[$propName] = "$colName int(11) NULL";
				break;
			case "text":
				$sqlDefs[$propName] = "$colName longtext NULL";
				break;
			case "lob":
				$sqlDefs[$propName] = "$colName longblob NULL";
				break;
			case "date":
				$sqlDefs[$propName] = "$colName datetime NULL";
				break;
			default:
				$sqlDefs[$propName] = "$colName varchar(255)";
				break;

			}
		}

		$sql .= implode(",\n",$sqlDefs);
		$sql .= "\n) ". $this->tableOpts.";";

		if ($this->collation != '') {
			$sqlStmt = array($sql,  "ALTER TABLE $tableName ".$this->collation);
		} else {
			$sqlStmt = array($sql);
		}

		//create unique key on multiple columns
		if ( count($dataitem->_uniqs ) ) {
			$sqlStmt[] = "CREATE UNIQUE INDEX ".$this->qc."unique_idx".$this->qc." ON ".$tableName." (".implode(',', $dataitem->_uniqs).") ";
		}

		return $sqlStmt;
	}

}
