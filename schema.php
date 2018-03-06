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

	public function getMissingColumns($tableDef, $dataitem) {
		$colNames = [];
		if (is_array($tableDef)) {
			if (array_key_exists('fields', $tableDef)) {
				foreach ($tableDef['fields'] as $_col) {
					$colNames[] = $_col['name'];
				}
			} else {
				foreach ($tableDef as $_col) {
					$colNames[] = $_col['name'];
				}
			}
		}
		$finalTypes = array();
		$vars       = get_object_vars($dataitem);
		$keys       = array_keys($vars);
		$fields     = array();
		$values     = array();
		foreach ($keys as $k) {
			if (substr($k,0,1) == '_') { continue; }
			//fix for SQLITE
			if (isset($dataitem->_pkey) && $k === $dataitem->_pkey && $vars[$k] == NULL ) {continue;}
			if (in_array($k, $colNames)) {
				//we don't need to alter existing columns
				continue;
			}
			if (array_key_exists($k, $dataitem->_typeMap)) {
				$finalTypes[$k] = $dataitem->_typeMap[$k];
			} else {
				$finalTypes[$k] = "string";
			}
		}

		$fieldList = [];
		foreach ($finalTypes as $k=>$type) {
			$fieldList[] = $this->schemaDriver->sqlDefForType($k, $type);
		}
		return $fieldList;
	}

	/**
	 * @TODO: dataitems don't specify indexes yet
	 */
	public function getMissingIndexes($tableDef, $dataitem) {
		return [];
	}

	public function getMissingUniques($tableDef, $dataitem) {
		return [];
	}

	public function makeTableDef($dataitem) {

		$tableName = $dataitem->_table;
		$fieldList    = [];
		$indexList    = [];

		$vars       = get_object_vars($dataitem);
		$keys       = array_keys($vars);

		if ($dataitem->_pkey !== NULL && ! array_key_exists($dataitem->_pkey, $keys)){
			$fieldList[] = array(
				'name'=>  $dataitem->_pkey,
				'type'=>  'int',
				'len' =>  11,
				'pk'  =>   1,
				'us'  =>   1,
				'def'  => NULL,
				'null' => FALSE);

		}
		foreach ($keys as $k) {
			if (substr($k,0,1) == '_') { continue; }

			if ($dataitem->_pkey !== NULL && $dataitem->_pkey == $k){
				continue;
			}

			if (array_key_exists($k, $dataitem->_typeMap)) {
				$type = $dataitem->_typeMap[$k];
			} else {
				$type = "string";
			}
			$fieldList[] = $this->schemaDriver->sqlDefForType($k, $type);
		}
		if ( @count($dataitem->_uniqs ) ) {
			$indexList[] = [
				'name'=>'unique_idx',
				'type'=>'unique',
				'cols'=>$dataitem->_uniqs
			];
		}

		return ['table'=>$tableName, 'fields'=>$fieldList, 'indexes'=>$indexList];
	}

	/**
	 * @return array list of table defs for many to many joins
	 */
	public function makeJoinTableDef($dataitem) {

		$tableList = [];
		if (!@count($dataitem->_relatedMany)) {
			return $tableList;
		}

		foreach ($dataitem->_relatedMany as $rel) {
			$tableName    = $rel['jtable'];
			$fieldList    = [];
			$indexList    = [];

			$fieldList[] = $this->schemaDriver->sqlDefForType($rel['fk'], 'int');
			$fieldList[] = $this->schemaDriver->sqlDefForType($rel['lk'], 'int');

			$tableList[] = ['table'=>$tableName, 'fields'=>$fieldList, 'indexes'=>$indexList];
		}

		return $tableList;
	}


	/**
	 * Create a number of SQL statements which will
	 * update the existing table to the required spec.
	 */
	public function dynamicAlterSql($tableDef, $dataitem) {
		$cols = $this->getMissingColumns($tableDef, $dataitem);
		$idx  = $this->getMissingIndexes($tableDef, $dataitem);
		$unq  = $this->getMissingUniques($tableDef, $dataitem);
		return $this->schemaDriver->getDynamicAlterSql($this->conn, $cols, $dataitem->_table);
	}

	/**
	 * Create a number of SQL statements which will
	 * create a new table
	 */
	public function dynamicCreateSql($dataitem) {
		$tableList = [$this->makeTableDef($dataitem)];
		$tableList += $this->makeJoinTableDef($dataitem);
		foreach ($tableList as $tableDef) {
			return $this->schemaDriver->getDynamicCreateSql($this->conn, $tableDef);
		}
	}
}
