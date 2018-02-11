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
		return ['table'=>$tableName, 'fields'=>$returnFields, 'indexes'=>[$indexList]];
	}
}
