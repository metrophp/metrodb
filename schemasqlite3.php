<?php
class Metrodb_Schemasqlite3 {

	public function _getTables($conn) {
		$conn->query("SELECT * FROM  sqlite_master where type='table'");

		$x = array();
		while ($conn->nextRecord()) {
			$x[] = $conn->record['tbl_name'];
		}
		return $x;
	}

	public function _getTableIndexes($conn, $tableName) {
		$conn->query("SELECT * FROM  sqlite_master where type='index' and tbl_name='$tableName'");
		$_idx = [];
		while ($conn->nextRecord()) {
			extract($conn->record);

			if (! array_key_exists($name, $_idx)) {
				$_idx[$name] = ['column'=>[], 'unique'=>FALSE];
			}
			$matches = [];
			//the 'sql' key holds the entire sql to recreate the
			//index, so the colums will be at the end inside
			//parentesis
			$cnt = preg_match('/.*\((.*)\)/', $sql, $matches);
			if (@$matches[1]) {
				$cols = explode(', ', $matches[1]);
				$cols = array_map(function($val) {
					return str_replace('"', '', $val);
				}, $cols);
				$_idx[$name]['column'] = $cols;
			}

			if (stristr('unique ', $sql)) {
				$_idx[$name]['unique'] = TRUE;
			} else {
				$_idx[$name]['unique'] = FALSE;
			}
		}
		return $_idx;
	}


	/**
	 * Return table definition in array format
	 *
	 * @return Array   keys are 'table', 'fields', 'indexes', 'uniques'
	 */
	public function _getTableDef($conn, $tableName) {

		//$dbfields = $conn->query("select * from sqlite_master where type='table' and tbl_name ='$tableName'");
		$dbfields = $conn->query("PRAGMA table_info($tableName)");
		$dbfields = [];
		while ($conn->nextRecord()) {
			$dbfields[] = $conn->record;
		}
		//mysqli_list_fields is deprecated, by more powerful than show columns
		#$dbfields = mysqli_list_fields($conn->database, $table, $conn->driverId);
		if (!$dbfields) {
			return FALSE;
		}
		$returnFields = array();
		foreach ($dbfields as $_st) {
			$name = $_st['name'];
			$type = $_st['type'];
			$size = '';
			/*
			if (strpos($type, '(') !== FALSE) {
				$size = substr($type, strpos($type, '(')+1,  (strpos($type, ')') -strpos($type, '(')-1) );
				$type = substr($type, 0, strpos($type, '('));
			}
			 */
			$def = $_st['dflt_value'];
			$flags = '';
			if ($def == '"NULL"') {
				$null = TRUE;
				$flags .= 'null ';
			} else {
				$null = FALSE;
				$flags .= 'not_null ';
			}

			if ($_st['pk'] == 1) {
				$flags .= 'auto_increment ';
			}

			$returnFields[] = array(
				'name'=>  $name,
				'type'=>  $type,
				'len' =>  $size,
				'flags'=> $flags,
				'def'  => $def,
				'null' => $null
			);
		}

		$indexList = $this->_getTableIndexes($conn, $tableName);
		return ['table'=>$tableName, 'fields'=>$returnFields, 'indexes'=>$indexList];
	}

	public function getDynamicAlterSql($conn, $cols, $tableName, $indexList=[], $uniqueList=[]) {
		$qc         = $conn->qc;
		$sqlDefs    = array();
		$finalTypes = $cols;


		$tableName = $qc.$tableName.$qc;
		foreach ($finalTypes as $_col) {
			$propName = $_col['name'];
			$type     = $_col['type'];
			if ($type == 'int') { $type = 'INTEGER'; }

			$colName  = $qc.$_col['name'].$qc;
			$sqlDefs[] = sprintf(
				"ALTER TABLE %s ADD COLUMN %s %s%s %s %s %s %s",
				$tableName,
				$propName,
				$type,
				//$_col['len'] ? "(".$_col['len'].")":"",
				@$_col['len'] ? "":"",
				//@$_col['us']  ? 'unsigned':'',
				@$_col['us'] == 1 ? '':'',
				@$_col['pk'] == 1 ? 'PRIMARY KEY':'',
				@$_col['pk'] == 1 && @$type == 'INTEGER' ? 'AUTOINCREMENT':'',
				$_col['null'] === TRUE ? 'NULL': 'NOT NULL',
				$_col['def']  !== NULL ? 'DEFAULT '.$_col['def']: ''
			);
		}

		if ($conn->collation != '') {
			$sqlDefs[] = "ALTER TABLE $tableName ".$conn->collation;
		}

		foreach ($uniqueList as $_index) {
			if (!@count($_index['cols'])) { continue; }
			$sqlStmt[] = "CREATE UNIQUE INDEX ".$qc.$_index['name'].$qc." ON ".$tableName."(".implode(',', $_index['cols']).") ";
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
			'null' => TRUE
		);

		switch ($type) {
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


	public function getDynamicCreateSql($conn, $tableDef) {
		$sql = "";
		$finalTypes = [];

		$tableDef['fields'][] = $this->sqlDefForType('created_on', 'ts');
		$tableDef['fields'][] = $this->sqlDefForType('updated_on', 'ts');
		//array('created_on'=>'ts', 'updated_on'=>'ts');
		$finalTypes = array_merge($finalTypes, $tableDef['fields']);

		$qc = $conn->qc;

		foreach($finalTypes as $_col) {
			$propName = $_col['name'];
			$type     = $_col['type'];
			$colName  = $qc.$_col['name'].$qc;
			if ($type == 'int') { $type = 'INTEGER'; }

			//$sqlDefs[$propName] = "$colName $type(".$_col['len'].") ".$_col['flags']." ".$col['NULL']." " .$_col['default']."";
			$sqlDefs[$propName] = sprintf(
				"%s %s%s %s %s %s %s",
				$colName,
				$type,
				@$_col['len'] ? "":"",
				@$_col['us']  ? '':'',
				@$_col['pk']  ? 'PRIMARY KEY':'',
				@$_col['pk'] && $type == 'INTEGER' ? 'AUTOINCREMENT':'',
				$_col['null'] === TRUE ? 'NULL': 'NOT NULL',
				$_col['def']  !== NULL ? 'DEFAULT '.$_col['def']: ''
			);
		}

		$tableName = $qc.$tableDef['table'].$qc;
		$sql = "CREATE TABLE IF NOT EXISTS ".$tableName." ( \n";
		$sql .= implode(",\n", $sqlDefs);
		$sql .= "\n) ". $conn->tableOpts.";";

		$sqlStmt = array($sql);

		foreach ($tableDef['indexes'] as $_index) {
			if (!@count($_index['cols'])) { continue; }
			$unq = $_index['type'] == 'unique'?'UNIQUE':'';

			$sqlStmt[] = "CREATE ".$unq." INDEX ".$qc.$_index['name'].$qc." ON ".$tableName."(".implode(',', $_index['cols']).") ";
		}
		return $sqlStmt;
	}
}
