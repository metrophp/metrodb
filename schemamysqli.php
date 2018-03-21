<?php

class Metrodb_Schemamysqli {

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
			if (! array_key_exists($Key_name, $_idx)) {
				$_idx[$Key_name] = ['column'=>[], 'unique'=>false];
			}
			$_idx[$Key_name]['column'][] = $Column_name;
			$_idx[$Key_name]['unique'] = $Non_unique == '1'? FALSE:TRUE;
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

		$dbfields = $conn->queryGetAll("show columns from `$tableName`");
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
		}

		$indexList = $this->_getTableIndexes($conn, $tableName);
		return ['table'=>$tableName, 'fields'=>$returnFields, 'indexes'=>$indexList];
	}

	public function getDynamicCreateSql($conn, $tableDef) {
		$sql = "";
		$finalTypes = [];

		$tableDef['fields'][] = $this->sqlDefForType('created_on', 'ts');
		$tableDef['fields'][] = $this->sqlDefForType('updated_on', 'ts');
		//array('created_on'=>'ts', 'updated_on'=>'ts');
		$finalTypes = array_merge($finalTypes, $tableDef['fields']);


		$qc  = $conn->qc;

		foreach($finalTypes as $_col) {
			$propName = $_col['name'];
			$type     = $_col['type'];
			if ($type == 'int') { $type = 'INTEGER'; }

			$colName  = $qc.$_col['name'].$qc;
			//$sqlDefs[$propName] = "$colName $type(".$_col['len'].") ".$_col['flags']." ".$col['NULL']." " .$_col['default']."";
			$sqlDefs[$propName] = sprintf("%s %s%s %s %s %s", 
				$colName,
				$type,
				@$_col['len'] ? "(".$_col['len'].")":"",
				@$_col['us']  ? 'unsigned':'',
				@$_col['pk']  ? 'PRIMARY KEY AUTO_INCREMENT':'',
				$_col['null'] === TRUE ? 'NULL': 'NOT NULL',
				$_col['def']  !== NULL ? 'DEFAULT '.$_col['def']: ''
			);
		}

		$tableName = $qc.$tableDef['table'].$qc;
		$sql = "CREATE TABLE IF NOT EXISTS ".$tableName." ( \n";
		$sql .= implode(",\n",$sqlDefs);
		$sql .= "\n) ". $conn->tableOpts.";";

		if ($conn->collation != '') {
			$sqlStmt = array($sql,  "ALTER TABLE $tableName ".$conn->collation);
		} else {
			$sqlStmt = array($sql);
		}

		foreach ($tableDef['indexes'] as $_index) {
			$sqlStmt[] = "ALTER TABLE ".$tableName." ADD UNIQUE INDEX ".$qc."unique_idx".$qc." (".implode(',', $_index['cols']).") ";
		}
		//create unique key on multiple columns
		/*
		if ( @count($dataitem->_uniqs ) ) {
			$sqlStmt[] = "ALTER TABLE ".$tableName." ADD UNIQUE INDEX ".$qc."unique_idx".$qc." (".implode(',', $dataitem->_uniqs).") ";
		}
		*/
		return $sqlStmt;
	}


	public function getDynamicAlterSql($conn, $cols, $dataitem, $indexList=[], $uniqueList=[]) {
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

		if ($conn->collation != '') {
			$sqlDefs[] = "ALTER TABLE $tableName ".$conn->collation.";";
		}

		foreach ($uniqueList as $_index) {
			if (!@count($_index['cols'])) { continue; }

			$sqlStmt[] = "ALTER TABLE ".$tableName." ADD UNIQUE INDEX ".$qc.$_index['name'].$qc." (".implode(',', $dataitem->_uniqs).") ";
		}

		return $sqlDefs;
	}

	public function sqlDefForType($name, $type) {

		$field = array(
			'name' => $name,
			'type' => '',
			'len'  => '',
			'us'   => 0,
			'pk'   => 0,
			'def'  => '',
			'null' => TRUE);

		switch($type) {
			case "email":
				$field['type'] = 'varchar';
				$field['len']  = '255';
				break;
			case "ts":
				$field['type']  = 'int';
				$field['len']   = '11';
				$field['def']   = 'NULL';
				$field['us']    = 1;
				break;
			case "int":
				$field['type']  = 'int';
				$field['len']   = '11';
				$field['def']   = 'NULL';
				$field['us']    = 1;
				break;
			case "text":
				$field['type']  = 'longtext';
				$field['def']   = 'NULL';
				break;
			case "lob":
				$field['type']  = 'longblob';
				$field['def']   = 'NULL';
				break;
			case "date":
				$field['type']  = 'datetime';
				$field['def']   = 'NULL';
				break;
			default:
				$field['type']  = 'varchar';
				$field['len']   = '255';
				break;
		}
		if ($field['def'] === '') {
			if ($field['null']) {
				$field['def'] = 'NULL';
			} else {
				$field['def'] = '\'\'';
			}
		}
		return $field;
	}
}
