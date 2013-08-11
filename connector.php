<?php
/**
 * This class loads specific mysql drivers
 * during the resources phase
 */
class Metrodb_Connector {

	public function resources(&$request) {
		associate_iAmA('dataitem', 'metrodb/dataitem.php');
		associate_iAmA('datamodel', 'metrodb/datamodel.php');
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

	// current *_fetch_array()-result.
	public $record = array();

	// current row number.
	public $row;

	public $RESULT_TYPE;

	// Error number when there's an error
	public $errorNumber;
	public $errorMessage = "";

	public $extraLogging = false;
	public $persistent = false;

	static public $connList = array(); //cache of objects per DSN


	static public function dsnForTable($tableName, $dsn='') {
		static $g_db_handle;
		if ($dsn)
			$g_db_handle[$tableName] = $dsn;

		if (!isset($g_db_handle[$tableName]))
			return 'default';

		return @$g_db_handle[$tableName];
	}

	/**
	 * Return a copy of a database connector object.
	 *
	 * Allow overriding of object creation from URIs by calling
	 *  the globally configured defaultDatabaseLayer in the object store
	 * @return  object  copy of a db object that has the settings of a DSN entry
	 */
	static public function &getHandle($dsn = 'default', $table='') {
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

		$connList = Metrodb_Connector::$connList;
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

	/**
	 * Create a new database connection from the given DSN and store it 
	 * internally in "connList" array.
	 */
	public function createHandle($dsn='default') {
		$t = associate_get($dsn.".dsn");
		if ( $t === NULL ) {
			return false;
		}


		$_dsn = parse_url($t);

		//make sure the driver is loaded
		$driver = $_dsn['scheme'];
		if (!class_exists('Metrodb_'.$driver, false)) {
			associate_iAmA($driver, 'metrodb/'.$driver.'.php');
		}
		$driver = associate_getMeA($driver);
		$driver->host = $_dsn['host'];
		$driver->database = substr($_dsn['path'],1);
		$driver->user = $_dsn['user'];
		$driver->password = @$_dsn['pass'];
		$driver->connect();

		Metrodb_Connector::$connList[$dsn] = $driver;
		return true;
	}


	/**
	 * Connect to the DB server
	 *
	 * Uses the classes internal host,user,password, and database variables
	 * @return void
	 *
	 * @abstract
	 */
	public function connect() {}


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
	public function exec($statementString) {}

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
	public function nextRecord($resId = false) {}


	/**
	 * Clean up resources for this result.
	 * Pop the top result off the stack.
	 *
	 * @abstract
	 */
	public function freeResult() {}


	/**
	 * Short hand for query() and nextRecord().
	 *
	 * @param string $sql SQL Command
	 */
	public function queryOne($sql) {}

	public function getAll($query) {
		return $this->queryGetAll($query);
	}

	public function queryGetAll($query, $report=TRUE) { 
		$this->query($query, $report);
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
	 * Short hand way to send a select statement.
	 *
	 * @param string $table  SQL table name
	 * @param string $fields  Column names
	 * @param string $where  Additional where clause
	 * @param string $orderby Optional orderby clause
	 */
	public function select($table, $fields = "*", $where = "", $orderby = "") {
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
	public function selectOne($table, $fields = "*", $where = "", $orderby = "") {
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


	public function executeQuery($query) {
		return $this->query($query->toString());
	}

	/**
	 * Return column definitions in array format
	 *
	 * @return Array   list of structures that define a table's columns.
	 */
	public function getTableColumns($table) {
		return array();
	}

	/**
	 * Prepare to stream a blob record
	 *
	 * @param string $table SQL table name
	 * @param string $col   SQL column name
	 * @param int    $id    Unique record id
	 * @param int    $pct   Size of each blob chunk as percentage of total
	 * @param string $idcol Name of column that holds identity if not table.'_id'
	 * @return array stream handle with info needed for nextChunk()
	 */
	public function prepareBlobStream($table, $col, $id, $pct=10, $idcol='') {
		if ($idcol == '') {$idcol = $table.'_id';}
		$this->queryOne('SELECT CHAR_LENGTH(`'.$col.'`) as `bitlen` from `'.$table.'` WHERE `'.$idcol.'` = '.sprintf('
			%d',$id));
		$ticket = array();
		$ticket['table'] = $table;
		$ticket['col']   = $col;
		$ticket['id']    = $id;
		$ticket['pct']   = $pct;
		$ticket['idcol'] = $idcol;
		$ticket['bytelen'] = $this->record['bitlen'];
		$ticket['finished'] = false;
		$ticket['byteeach'] = floor($ticket['bytelen'] * ($pct / 100));
		$ticket['bytelast']  = $ticket['bytelen'] % ((1/$pct) * 100);
		$ticket['pctdone'] = 0;
		return $ticket;
	}

	/**
	 * Select a percentage of a blob field
	 *
	 * @param $ticket required array from prepareBlobStream()
	 */
	public function nextStreamChunk(&$ticket) {
		if ($ticket['finished']) { return false; }

		$_x = (floor($ticket['pctdone']/$ticket['pct']) * $ticket['byteeach']) + 1;
		$_s = $ticket['byteeach'];

		if ($ticket['finished'] == TRUE) {
			return NULL;
		}

		if ($ticket['pctdone'] + $ticket['pct'] >= 100) {
			//grab the uneven bits with this last pull
			$_s += $ticket['bytelast'];
			$this->queryOne('SELECT SUBSTR(`'.$ticket['col'].'`,'.$_x.') 
				AS `blobstream` FROM '.$ticket['table'].' WHERE `'.$ticket['idcol'].'` = '.sprintf('%d',$ticket['id']));
		} else {
			$this->queryOne('SELECT SUBSTR(`'.$ticket['col'].'`,'.$_x.','.$_s.') 
				AS `blobstream` FROM '.$ticket['table'].' WHERE `'.$ticket['idcol'].'` = '.sprintf('%d',$ticket['id']));
		}
		$ticket['pctdone'] += $ticket['pct'];
		if ($ticket['pctdone'] >= 100) { 
			$ticket['finished'] = TRUE;
		}
		return $this->record['blobstream'];
	}
}
