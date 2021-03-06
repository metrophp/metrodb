<?php
/**
 * This class loads specific mysql drivers
 * during the resources phase
 */
class Metrodb_Connector {

	public $qc            = '"';
	public $collation     = '';
	public $tableOpts     = '';
	public $dynamicSchema = FALSE;

	public function resources($request) {
		_didef('dataitem',  'metrodb/dataitem.php');
		_didef('datamodel', 'metrodb/datamodel.php');
	}

	/**
	 * Result of *_connect() function
	 */
	public $driverId = 0;

	/**
	 * List of result sets that stack until
	 * there are no more records
	 */
	public $resultSet = array();

	public static $listDsn   = array();

	// current *_fetch_array()-result.
	public $record = array();

	// current row number.
	public $row;

	public $RESULT_TYPE;

	// Error number when there's an error
	public $errorNumber;
	public $errorMessage = "";

	public $extraLogging = FALSE;
	public $persistent = FALSE;

	static public $connList = array(); //cache of objects per DSN

	static public $logList = array();
	public $log            = NULL;


	static public function dsnForTable($tableName, $dsn='') {
		static $g_db_handle;
		if ($dsn)
			$g_db_handle[$tableName] = $dsn;

		if (!isset($g_db_handle[$tableName]))
			return 'default';

		return @$g_db_handle[$tableName];
	}

	public function setDynamicSchema() {
		$this->dynamicSchema = TRUE;
	}

	/**
	 * Return a copy of a database connector object.
	 *
	 * Allow overriding of object creation from URIs by calling
	 *  the globally configured defaultDatabaseLayer in the object store
	 * @return  object  copy of a db object that has the settings of a DSN entry
	 */
	static public function getHandle($dsn = 'default', $table='') {
		if ($dsn == NULL && $table != '') {
			$dsn = Metrodb_Connector::dsnForTable($table);
		}

		$connList = Metrodb_Connector::$connList;

		// if a connection has already been made use it
		if (@!is_object($connList[$dsn]) ) {
			//createHandles stores the ref in connList
			if (!Metrodb_Connector::createHandle($dsn) ) {
				$dsn = 'default';
			}
		}
		$x =& Metrodb_Connector::$connList[$dsn];
		if (!$x) {
			//don't return false, always return some kind of object.
			return new Metrodb_Connector();
		}

		// optimize the next two lines by only executing them on PHP5
		// 4 already makes a shallow copy.
		$phpv = phpversion();
		if ($phpv[0] >= 5) {
			$copy = clone $x;
		} else {
			$copy = $x;
			$copy->resultSet = array();
		}

		//return by value (copy) to make sure
		// nothing has access to old query results
		// keeps the same connection Id though
		return $copy;
	}

	static public function setLoggerForDsn($dsn, $log) {
		Metrodb_Connector::$logList[$dsn] = $log;
	}

	public function setLogger($log) {
		$this->log = $log;
	}

	/**
	 * Return a reference of a database connector object.
	 *
	 * Allow overriding of object creation from URIs by calling
	 *  the globally configured defaultDatabaseLayer in the object store
	 * @return  object  ref of a db object that has the settings of a DSN entry
	 */
	public function& getHandleRef($dsn = 'default', $table='') {
		if ($dsn == NULL && $table != '') {
			$dsn = Metrodb_Connector::dsnForTable($table);
		}

		$connList =& Metrodb_Connector::$connList;
		// if a connection has already been made and in the handles array
		// get it out

		if (@!is_object($connList[$dsn]) ) {
			//createHandles stores the ref in connList
			if (!Metrodb_Connector::createHandle($dsn) ) {
				$dsn = 'default';
			}
		}
		return $connList[$dsn];
	}

	public static function setDsn($dsn, $url) {
		self::$listDsn[$dsn] = $url;
	}

	public static function getDsn($dsn) {
		$x = NULL;
		if (function_exists('_get')) {
			$x = _get($dsn.".dsn", NULL);
		}
		if (!$x && array_key_exists($dsn,  self::$listDsn)) {
			return self::$listDsn[$dsn];
		}
		return $x;
	}

	public static function loadDriver($driver) {
		if (function_exists('_make')) {
			if (!class_exists('Metrodb_'.$driver, FALSE)) {
				_didef($driver, 'metrodb/'.$driver.'.php');
			}
			return _make($driver);
		} else {
			$className = 'Metrodb_'.$driver;
			if (class_exists('Metrodb_'.$driver, TRUE)) {
				return new $className;
			} else {
				include_once( dirname(__FILE__).'/'.$driver.'.php');
				return new $className;
			}
			return NULL;
		}
	}

	/**
	 * Create a new database connection from the given DSN and store it
	 * internally in "connList" array.
	 */
	public static function createHandle($dsn='default') {
		$t = Metrodb_Connector::getDsn($dsn);

		if ( $t === NULL ) {
			return FALSE;
		}

		$_dsn = parse_url($t);

		//make sure the driver is loaded
		$driverName = $_dsn['scheme'];
		$driver = self::loadDriver($driverName);
		$driver->host = @$_dsn['host'];
		$driver->database = substr($_dsn['path'],1);
		$driver->user = @$_dsn['user'];
		$driver->password = @$_dsn['pass'];
		$options = array();
		if (array_key_exists('query', $_dsn)) {
			parse_str($_dsn['query'], $options);
		}

		if (array_key_exists('port', $_dsn)) {
			$driver->port = $_dsn['port'];
		}

		//set logger before trying to connect
		if (array_key_exists($dsn, Metrodb_Connector::$logList)) {
			$driver->setLogger(Metrodb_Connector::$logList[$dsn]);
		} else {
			if (array_key_exists('default', Metrodb_Connector::$logList)) {
				$driver->setLogger(Metrodb_Connector::$logList['default']);
			}
		}

		try {
			$driver->connect($options);
		} catch (Exception $e) {
			//probably database not available.
		}

		Metrodb_Connector::$connList[$dsn] = $driver;
		return TRUE;
	}

	/**
	 * Call set* for every key in the array
	 * The array is made from query params to the DSN
	 * mysql://user:pw@localhost:3306/dbname?opt&opt2
	 * will call $this->setOpt() and $this->setOpt2()
	 */
	public function setOptions($options) {
		foreach ($options as $_key => $_val) {
			$method = 'set'.ucfirst($_key);
			if (is_callable(array($this, $method))) {
				call_user_func( array($this, $method));
			}
		}
	}

	/**
	 * Connect to the DB server
	 *
	 * Uses the classes internal host,user,password, and database variables
	 * @return void
	 *
	 * @abstract
	 */
	public function connect($options) {}


	/**
	 * Send query to the DB
	 *
	 * Results are stored in $this->resultSet;
	 * @return  void
	 * @param  string $queryString SQL command to send
	 *
	 * @abstract
	 */
	public function query($queryString) {}

	/**
	 * Send a statement to the DB
	 *
	 * Do not expect a result set
	 * @return  void
	 * @param  string $statementString  SQL command to send
	 */
	public function exec($statementString, $bind=NULL) {}

	/**
	 * Close connection
	 *
	 * @return void
	 */
	public function close() {
	}

	/**
	 * Close connection
	 *
	 * @return void
	 */
	public function disconnect() {
		return $this->close();
	}

	/**
	 * Grab the next record from the resultSet
	 *
	 * Returns true while there are more records, false when the set is empty
	 * Automatically frees result when the result set is emtpy
	 * @return boolean
	 * @param  int $resId Specific resultSet, default is last query
	 *
	 * @abstract
	 */
	public function nextRecord($resId = FALSE) {}


	/**
	 * Clean up resources for this result.
	 * Pop the top result off the stack.
	 *
	 * @abstract
	 */
	public function freeResult() {}


	public function queryGetAll($query) {
		$this->query($query);
		$rows = array();
		while($this->nextRecord()) {
			$rows[] = $this->record;
		}
		return $rows;
	}

	public function fetchAll() {
		$rows = array();
		while($this->nextRecord()) {
			$rows[] = $this->record;
		}
		return $rows;
	}

	/**
	 * Moves resultSet cursor to beginning
	 * @return void
	 * @abstract
	 */
	public function reset() {}


	/**
	 * Moves resultSet cursor to an aribtrary position
	 *
	 * @param int $row Desired index offset
	 * @return void
	 * @abstract
	 */
	public function seek($row) {}


	/**
	 * Retrieves last error message from the DB
	 *
	 * @return string Error message
	 * @abstract
	 */
	public function getLastError() {}


	/**
	 * Return the last identity field to be created
	 *
	 * @return mixed
	 * @abstract
	 */
	public function getInsertId() {}


	/**
	 * Return the number of rows affected by the last query
	 *
	 * @return int number of affected rows
	 * @abstract
	 */
	public function getNumRows() {}


	/**
	 * for commands like "set FLAG=1" or "BEGIN TRANSACTION"
	 * @see executeStatement($stmt)
	 */
	public function execute($stmt, $bind=NULL) {
		return $this->executeStatement($stmt, $bind);
	}

	/**
	 * for commands like "set FLAG=1" or "BEGIN TRANSACTION"
	 * @param $stmt mixed either String or Object with toString()
	 */
	public function executeStatement($stmt, $bind=NULL) {
		if (is_object($stmt)) {
			return $this->exec($stmt->toString(), $bind);
		} else {
			return $this->exec($stmt, $bind);
		}
	}

	public function executeQuery($query) {
		if (is_object($query)) {
			return $this->query($query->toString());
		} else {
			return $this->query($query);
		}
	}

	public function rollbackTx() {
		$this->exec("ROLLBACK TRANSACTION");
	}

	public function startTx() {
		$this->exec("BEGIN TRANSACTION");
	}

	public function commitTx() {
		$this->exec("COMMIT TRANSACTION");
	}

	public function truncate($tbl) {
		$qc = $this->qc;
		return $this->exec("TRUNCATE ".$qc.$tbl.$qc);
	}

	public function getSchema() {
		$thisClassNameParts = explode('_', get_class($this));
		$driverName         = strtolower($thisClassNameParts[1]);
		$driverClassName    = 'Metrodb_Schema'.$driverName;
		if (class_exists('Metrodb_Schema'.$driverName, TRUE)) {
			$driver = new $driverClassName;
		} else {
			include_once( dirname(__FILE__).'/schema'.$driverName.'.php');
			$driver = new $driverClassName;
		}
		include_once( dirname(__FILE__).'/schema.php');
		$schema = new Metrodb_Schema($this, $driver );
		return $schema;
	}

	public function escapeCharValue($val) {
		return "'".addslashes(
			$val
		)."'";
	}

	public function escapeBinaryValue($val) {
		return '\''.addcslashes(
			$val,
			"\x00\'\"\r\n"
		).'\'';
	}

	/**
	 * Add columns at runtime, or create a missing table.
	 *
	 * @param Object $di  the dataitem to use as schema
	 */
	public function onSchemaError($di) {
		$s          = $this->getSchema();
		$tableDef   = $s->getTable($di->_table);
		$tableDiffs = $s->getDifference($di, $tableDef);

		if ($this->dynamicSchema) {
			$sqlDefs   = $s->renderDifference($tableDiffs);
			foreach ($sqlDefs as $sql) {
				$this->query($sql);
			}
			return TRUE;
		}
		if (function_exists('_isdidef') && _isdidef('migrationWriter')) {
			$writer = _make('migrationWriter');
			$sqlDefs   = $s->renderDifference($tableDiffs, $writer);
			return TRUE;
		}

		if ($this->log) {
			$this->log->error("Schema error. Either enable dynamicSchema or define a migrationWriter.", [$tableDiffs]);
			return FALSE;
		}

		throw new \Exception("Schema error. Either enable dynamicSchema or define a migrationWriter.");
	}
}
