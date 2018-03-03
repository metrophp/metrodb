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
		return $this->schemaDriver->getDynamicAlterSql($this->conn, $cols, $dataitem);
	}

	/**
	 * Create a number of SQL statements which will
	 * create a new table
	 */
	public function dynamicCreateSql( $dataitem) {
		return $this->schemaDriver->getDynamicCreateSql($this->conn, $dataitem);
	}
}
