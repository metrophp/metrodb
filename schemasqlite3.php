<?php
class Metrodb_Schemasqlite3 {

	public function _getTables($conn) {
		$conn->query("show tables");
		$j = $conn->RESULT_TYPE;
		$conn->RESULT_TYPE = MYSQLI_BOTH;

		$x = array();
		while ($conn->nextRecord()) {
			$x[] = $conn->record[0];
		}
		$conn->RESULT_TYPE = $j;
		return $x;
	}

	public function _getTableIndexes($conn, $tableName) {
		$conn->query("show index from `$tableName`");
		$_idx = [];
		while ($conn->nextRecord()) {
			extract($conn->record);
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
	public function _getTableColumns($conn, $tableName) {
		return $this->_getTableDef($conn, $tableName);
	}

	public function _getTableDef($conn, $tableName) {

		$dbfields = $conn->queryGetAll("PRAGMA table_info($tableName)");
		//mysqli_list_fields is deprecated, by more powerful than show columns
		#$dbfields = mysqli_list_fields($conn->database, $table, $conn->driverId);
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
			/*
			if (stripos($_st['Type'], 'unsigned') !== FALSE) {
//				$flags .= 'unsigned ';
			}
			 */
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
		}

		$indexList = $this->_getTableIndexes($conn, $tableName);
		return ['table'=>$tableName, 'fields'=>$returnFields, 'indexes'=>[$indexList]];
	}

	public function getDynamicAlterSql($conn, $cols, $dataitem) {
		$qc         = $conn->qc;
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
					ADD COLUMN $propName int(11) NULL DEFAULT NULL; \n";
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

		if ($conn->collation != '') {
			$sqlDefs[] = "\n\nALTER TABLE $tableName ".$conn->collation.";";
		}

		return $sqlDefs;
	}


	public function getDynamicCreateSql($conn, $dataitem) {
		$sql = "";
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

		$qc = $conn->qc;
		$tableName = $qc.$dataitem->_table.$qc;
		/**
		 * build SQL
		 */
		$sql = "CREATE TABLE IF NOT EXISTS ".$tableName." ( \n";

		if ($dataitem->_pkey !== NULL && ! array_key_exists($dataitem->_pkey, $finalTypes)) {
			$sqlDefs[$dataitem->_pkey] = $qc.$dataitem->_pkey.$qc." INTEGER PRIMARY KEY AUTOINCREMENT";
		}

		foreach($finalTypes as $propName=>$type) {
			$colName = $qc.$propName.$qc;
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
		$sql .= "\n) ". $conn->tableOpts.";";

		if ($conn->collation != '') {
			$sqlStmt = array($sql,  "ALTER TABLE $tableName ".$conn->collation);
		} else {
			$sqlStmt = array($sql);
		}

		//create unique key on multiple columns
		if ( count($dataitem->_uniqs ) ) {
			$sqlStmt[] = "CREATE UNIQUE INDEX ".$qc."unique_idx".$qc." ON ".$tableName." (".implode(',', $dataitem->_uniqs).") ";
		}

		return $sqlStmt;
	}

}
