<?php

class Metrodb_Schema {

	protected $conn;
	protected $schemaDriver;

	public function __construct($dsn, $driver) {
		if (is_object($dsn)) {
			$this->setConnection( $dsn );
		} else {
			$this->setConnection( Metrodb_Connector::getHandle($dsn) );
		}
		$this->setSchemaDriver( $driver );
	}

	public function setSchemaDriver($d) {
		$this->schemaDriver = $d;
	}

	public function setConnection($c) {
		$this->conn = $c;
	}

	public function getTables() {
		$tableList    = $this->schemaDriver->_getTables($this->conn);
		$tableDefList = [];
		foreach ($tableList as $_t) {
			$tableDefList[] = $this->getTable($_t);
		}
		return $tableDefList;
	}

	public function getTable($table) {
		$table = $this->schemaDriver->_getTableDef($this->conn, $table);
		return $table;
	}

	public function getTableColumns($table) {
		$table = $this->schemaDriver->_getTableDef($this->conn, $table);
		return $table['fields'];
	}

	/**
	 * Create a number of SQL statements which will
	 * update the existing table to the required spec.
	 */
	public function dynamicAlterSql($cols, $dataitem) {
		$qc         = $this->conn->qc;
		$sqlDefs    = array();
		$finalTypes = array();
		$colNames   = array();

		foreach ($cols as $_col) {
			$colNames[] = $_col['name'];
		}

		$finalTypes = array();
		$vars = get_object_vars($dataitem);
		$keys = array_keys($vars);
		$fields = array();
		$values = array();
		foreach ($keys as $k) {
			if (substr($k,0,1) == '_') { continue; }
			//fix for SQLITE
			if (isset($dataitem->_pkey) && $k === $dataitem->_pkey && $vars[$k] == NULL ) {continue;}
			if (in_array($k, $colNames)) {
				//we don't need to alter existing columsn
				continue;
			}
			if (array_key_exists($k, $dataitem->_typeMap)) {
				$finalTypes[$k] = $dataitem->_typeMap[$k];
			} else {
				$finalTypes[$k] = "string";
			}
		}

		$tableName = $qc.$dataitem->_table.$qc;
		/**
		 * build SQL
		 */
		foreach($finalTypes as $propName=>$type) {
			$propName  = $qc.$propName.$qc;
			switch($type) {
			case "email":
				$sqlDefs[] = "ALTER TABLE $tableName
					ADD COLUMN $propName VARCHAR(255)  NULL DEFAULT NULL; \n";
				break;
			case "ts":
				$sqlDefs[] = "ALTER TABLE $tableName
					ADD COLUMN $propName int(11) unsigned NULL DEFAULT NULL; \n";
				break;
			case "int":
				$sqlDefs[] = "ALTER TABLE $tableName
					ADD COLUMN $propName int(11) NULL DEFAULT NULL; \n";
				break;
			case "text":
				$sqlDefs[] = "ALTER TABLE $tableName
					ADD COLUMN $propName longtext NULL; \n";
				break;
			case "lob":
				$sqlDefs[] = "ALTER TABLE $tableName
					ADD COLUMN $propName longblob NULL; \n";
				break;
			case "date":
				$sqlDefs[] = "ALTER TABLE $tableName
					ADD COLUMN $propName datetime NULL DEFAULT NULL; \n";
				break;
			default:
				$sqlDefs[] = "ALTER TABLE $tableName
					ADD COLUMN $propName VARCHAR(255) NULL DEFAULT NULL; \n";
				break;

			}
		}

		if ($this->conn->collation != '') {
			$sqlDefs[] = "\n\nALTER TABLE $tableName ".$this->conn->collation.";";
		}

		return $sqlDefs;
	}

	public function dynamicCreateSql($dataitem) {
		$qc  = $this->conn->qc;
		$sql = "";
		//$props = $dataitem->__get_props();
		$finalTypes = array('created_on'=>'ts', 'updated_on'=>'ts');

		$vars   = get_object_vars($dataitem);
		$keys   = array_keys($vars);
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

		$tableName = $qc.$dataitem->_table.$qc;
		/**
		 * build SQL
		 */
		$sql = "CREATE TABLE IF NOT EXISTS ".$tableName." ( \n";

		if ($dataitem->_pkey !== NULL && ! array_key_exists($dataitem->_pkey, $finalTypes)) {
			$sqlDefs[$dataitem->_pkey] = $qc.$dataitem->_pkey.$qc." int(11) unsigned auto_increment primary key";
		}

		foreach($finalTypes as $propName=>$type) {
			$colName = $qc.$propName.$qc;
			switch($type) {
			case "email":
				$sqlDefs[$propName] = "$colName varchar(255)";
				break;
			case "ts":
				$sqlDefs[$propName] = "$colName int(11) unsigned NULL DEFAULT NULL";
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
		$sql .= "\n) ". $this->conn->tableOpts.";";

		if ($this->conn->collation != '') {
			$sqlStmt = array($sql,  "ALTER TABLE $tableName ".$this->conn->collation);
		} else {
			$sqlStmt = array($sql);
		}

		//create unique key on multiple columns
		if ( count($dataitem->_uniqs ) ) {
			$sqlStmt[] = "ALTER TABLE ".$tableName." ADD UNIQUE INDEX ".$qc."unique_idx".$qc." (".implode(',', $dataitem->_uniqs).") ";
		}
		return $sqlStmt;
	}
}
