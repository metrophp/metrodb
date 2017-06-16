<?php
/**
 * The Metrodb_Dataitem class is a wrapper for sets of SQL data.
 * The item can *load *a single row, or *find* many rows
 * from multiple tables.
 *
 * Usage:
 *
 * $finder = new Metrodb_Dataitem('table'); //with primary key table_id
 * $finder->andWhere('status', $statusIdList, 'IN'); //statusIdList is an array
 * $finder->orderBy('created_on DESC'); //only one parameter for ASC/DESC
 * $rows = $finder->findAsArray();
 * $objects = $finder->find();
 * $count = $finder->getUnlimitedCount(); //requery with count(*) and no limit
 */

class Metrodb_Dataitem {

	public $_table;
	public $_pkey;
	public $_relatedMany   = array();
	public $_relatedSingle = array();
	public $_typeMap       = array();
	public $_where         = array();		//list of where sub-arrays
	public $_excludes      = array();		//list of columns not to select
	public $_cols          = array();		//list of columns for selects
	public $_nuls          = array();		//list of columns that can hold null
	public $_bins          = array();		//list of columns that can hold binary
	public $_uniqs         = array();		//list of columns that, together, act as a primary key
	public $_limit         = -1;
	public $_start         = -1;
	public $_sort          = array();
	public $_groupBy       = array();
	public $_filterNames   = TRUE;
	public $_tblPrefix     = '';
	public $_isNew         = FALSE;
	public $_rsltByPkey    = FALSE;
	//	public $_dsnName       = 'default';


	/**
	 * Initialize a new data item.
	 *
	 * Sets "_isNew" to true, load() and find() set _isNew to false
	 *
	 * @param String $table 	the name of the table in the database
	 * @param String $pkey 		if left empty, pkey defaults to $t.'_id', if NULL no auto-increment will be used
	 * @constructor
	 * @see load
	 * @see find
	 */
	public function __construct($t, $pkey='') {
		$this->_table = $t;
		//if pkey is null we don't want 1 auto-increment primary key
		if ($pkey !== NULL) {
			$this->_pkey = $pkey;
			if (!$this->_pkey) {
				//if we didn't specify, we just have ''
				$this->_pkey = $this->_table.'_id';
			}
			//set the pkey to null to stop notices
			$this->{$this->_pkey} = NULL;
		}
		$this->_isNew = TRUE;
	}

	/**
	 * Quiet warnings on some PHP systems by
	 * doing what should be done when an empty
	 * key is accessed anyway.
	 */
    public function __get($key) {
        if (!isset($this->{$key})) {
            return NULL;
        }
        return $this->{$key};
    }

	/**
	 * Return all the values as an array
	 */
	public function valuesAsArray() {
		$vars = get_object_vars($this);
		$keys = array_keys($vars);
		$values = array();
		foreach ($keys as $k) {
			//skip private and data item specific members
			if (substr($k,0,1) == '_') { continue; }
			$values[$k] = $vars[$k];
		}
		return $values;
	}

	/**
	 * If this table has no auto-incrment primary key, the
	 * combined values of these columns shall be considered
	 * unique.
	 *
	 * @param Array $cols  a list of cols that act as a primary key
	 */
	public function setUniqueCols($cols) {
		$this->_uniqs = $cols;
	}

	/**
	 * Set this object's primary key field
	 */
	public function setPrimaryKey($n) {
		$this->{$this->_pkey} = $n;
	}

	/**
	 * Get this object's primary key field
	 */
	public function getPrimaryKey() {
		return @$this->{$this->_pkey};
	}

	/**
	 * Return the data's kind, the sql table
	 */
	public function getKind() {
		return $this->_table;
	}

	/**
	 * Set a property of this data item.
	 *
	 * @param $key string  column name
	 * @param $val mixed   any value
	 */
	public function set($key, $value) {
		$this->{$key} = $value;
	}

	/**
	 * Return a value of this data item.
	 *
	 * @return mixed  value of data item's property
	 */
	public function get($key) {
		if(isset($this->{$key})) {
			return $this->{$key};
		} else {
			return NULL;
		}
	}

	public function startTx() {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);
		$db->startTx();
	}

	public function commitTx() {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);
		$db->commitTx();
	}

	public function rollbackTx() {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);
		$db->rollbackTx();
	}

	/**
	 * Insert or update
	 *
	 * @return mixed FALSE on failure, integer primary key on success
	 */
	public function save() {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);

		if ( $this->_isNew ) {
			if (!$db->query( $this->buildInsert(), FALSE )) {
				$err = $db->errorMessage;
				if (!$this->dynamicResave($db)) {
					return FALSE;
				}
			}
			$this->_isNew = FALSE;
			if (!isset($this->_pkey) || $this->_pkey === NULL) {
				//don't return a pkey if none is defined
				return TRUE;
			} else {
				$this->{$this->_pkey} = $db->getInsertId();
			}
		} else {
			if (!$db->query( $this->buildUpdate(), FALSE )) {
				$err = $db->errorMessage;
				// TRUE performs buildUpdate instead of buildInsert
				if (!$this->dynamicResave($db, TRUE)) {
					return FALSE;
				}
			}
			$this->_isNew = FALSE;
			if (!isset($this->_pkey) || $this->_pkey === NULL) {
				//don't return a pkey if none is defined
				return TRUE;
			}
		}
		return $this->{$this->_pkey};
	}


	/**
	 * Load one record from the DB
	 *
	 * @param string $where  Optional: if an array, it is imploded with " and ",
	 *   if it is a string, it is added as a condition for the pkey
	 */
	public function load($where='') {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);
		$whereQ = '';

		//if something is passed in (not ''),
		//but it is null or 0, then we need not to
		//load anything because the calling script is probably expecting
		//an ID from a loop or an array.  There is no need to explicitly
		//pass NULL or 0 to this method.
		if (count($this->_where) < 1) {
			if ($where === NULL) {
				return FALSE;
			}
			if ($where === 0) {
				return FALSE;
			}
		}

		if (is_array($where) ) {
			$whereQ = implode(' and ',$where);
		} else if (strlen($where) ) {
			$whereQ = $this->_pkey .' = '.$where;
		} else if (!isset($this->_pkey) || $this->_pkey === NULL) {
			$atom = '';
			foreach ($this->_uniqs as $uni) {
				$struct = array('k'=>$uni, 'v'=> $this->get($uni), 's'=>'=', 'andor'=>'and', 'q'=>true);
				$atom = $this->_whereAtomToString($struct, $atom);
			}
			//causes problems on sqlite
			//$whereQ .= $atom .' LIMIT 1';
			$whereQ .= $atom;
		}

		if (!$db->query( $this->buildSelect($whereQ), FALSE )) {
			$err = $db->errorMessage;
//			$errObj = Metrofw_ErrorStack::pullError();
			if (!$this->dynamicReload($db, $whereQ)) {
				//pulling the db error hides the specifics of the SQL
/*				if (Metrofw_ErrorStack::pullError()) {
					Metrofw_ErrorStack::throwError("Cannot load data item.\n".
						$err, E_USER_WARNING);
				}
 */
				return false;
			}
		}

		if(!$db->nextRecord()) {
			return false;
		}
		$db->freeResult();
		if (empty($db->record)) {
			return false;
		}
		$this->row2Obj($db->record);
		$this->_isNew = false;
		return TRUE;
	}

	/**
	 * Load one record from the DB where the row matches all set values.
	 */
	public function loadExisting() {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);

		$vals = $this->valuesAsArray();
		foreach ($vals as $_k => $_v) {
			//skip null pkeys, we are looking for matching pkey
			if (isset($this->_pkey) && $_k === $this->_pkey && $vals[$_k] == NULL ) {continue;}
			if (in_array($_k,$this->_nuls) && $vals[$_k] == NULL ) {
				$this->andWhere($_k, NULL, 'IS');
			} else {
				$this->andWhere($_k, $_v);
			}
		}
		$db->query( $this->buildSelect() );
		if(!$db->nextRecord()) {
			return false;
		}
		$db->freeResult();
		if (empty($db->record)) {
			return false;
		}
		$this->row2Obj($db->record);
		$this->_isNew = false;
		return TRUE;
	}

	/**
	 * Load multiple records from the DB
	 *
	 * @param string $where  Optional: if an array, it is imploded with " and ",
	 *   if it is a string it is treated as the first part of the where clause
	 */
	public function find($where='') {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);
		$whereQ = '';
		if (is_array($where) ) {
			$whereQ = implode(' and ',$where);
		} else {
			$whereQ = $where;
		}
		/*
		} else if (strlen($where) ) {
			$whereQ = $this->_pkey .' = '.$where;
		 */
		if (!$db->query( $this->buildSelect($whereQ), FALSE )) {
			$err = $db->errorMessage;
			if (!$this->dynamicReload($db, $whereQ)) {
				return array();
			}
		}


		$objs = array();

		if(!$db->nextRecord()) {
			return $objs;
		}

		do {
			$x = new Metrodb_Dataitem($this->_table,$this->_pkey);
			$x->_excludes = $this->_excludes;
			$x->_nuls     = $this->_nuls;
			$x->row2Obj($db->record);
			$x->_isNew = false;
			if ( $this->_rsltByPkey == TRUE) {
				if (! isset($db->record[$x->_pkey])) {
					$objs[] = $x;
				} else {
					$objs[$db->record[$x->_pkey]] = $x;
				}
			} else {
				$objs[] = $x;
			}
		} while ($db->nextRecord());
		return $objs;
	}

	/**
	 * Load multiple records from the DB
	 *
	 * @param string $where  Optional: if an array, it is imploded with " and ",
	 *   if it is a string it is treated as the first part of the where clause.
	 *
	 * @return Array  a list of records as an associative array
	 */

	public function findAsArray($where='') {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);
		$whereQ = '';
		if (is_array($where) ) {
			$whereQ = implode(' and ',$where);
		} else {
			$whereQ = $where;
		}
		/*
		} else if (strlen($where) ) {
			$whereQ = $this->_pkey .' = '.$where;
		 */
		if (!$db->query( $this->buildSelect($whereQ), FALSE )) {
			$err = $db->errorMessage;
//			$errObj = Metrofw_ErrorStack::pullError();
			if (!$this->dynamicReload($db, $whereQ)) {
				//pulling the db error hides the specifics of the SQL
/*				if (Metrofw_ErrorStack::pullError()) {
					Metrofw_ErrorStack::throwError("Cannot load data item.\n".
						$err, E_USER_WARNING);
				}
 */
				return array();
			}
		}


		$recs = array();

		if(!$db->nextRecord()) {
			return $recs;
		}

		do {
			$x = $db->record;
			if ( $this->_rsltByPkey == TRUE) {
				if (! isset($db->record[$this->_pkey])) {
					$recs[] = $x;
				} else {
					$recs[$db->record[$this->_pkey]] = $x;
				}
			} else {
				$recs[] = $x;
			}
		} while ($db->nextRecord());
		return $recs;
	}


	/**
	 * If this object was loaded (_isNew == false) {
	 * then delete using this object's pkey
	 * else delete with the current whereAtoms
	 */
	public function delete($whereQ='') {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);

		//refuse to delete entire table
		if ( $this->_isNew && $whereQ == '' && count($this->_where) == 0) {
			return FALSE;
		}

		return $db->query( $this->buildDelete($whereQ) );
	}

	public function getUnlimitedCount($where='') {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);
		$whereQ = '';
		if (is_array($where) ) {
			$whereQ = implode(' and ',$where);
		} else if (strlen($where) ) {
			$whereQ = $this->_pkey .' = '.$where;
		}

		$db->query( $this->buildCountSelect($whereQ) );
		if(!$db->nextRecord()) {
			return false;
		}
		if (empty($db->record)) {
			$db->freeResult();
			return false;
		}

		$count = $db->record['total_rec'];
		//some group by clauses split the count(*) up into
		// multiple rows. If this query
		// has a group by return the size of the result set
		if (count($this->_groupBy) > 0) {
			$count = $db->getNumRows();
		}
		$db->freeResult();
		return $count;
	}


	public function row2Obj($row) {
		foreach ($row as $k=>$v) {
			if (in_array($k,$this->_excludes)) { continue; }
			//optionally translate k to k prime
			$this->{$k} = $v;
		}
		$this->_isNew = false;
	}


	public function getTable() {
		return $this->_tblPrefix.$this->_table;
	}


	public function buildSelect($whereQ='') {
		if (count($this->_cols) > 0) {
			$cols = implode(',',$this->_cols);
		} else {
			$cols = '*';
		}
		return "SELECT ".$cols." FROM ".$this->getTable()." ".$this->buildJoin(). " ".$this->buildWhere($whereQ). " ". $this->buildGroup(). " ". $this->buildSort() ." " . $this->buildLimit();
	}

	public function buildCountSelect($whereQ='') {
		$cols = 'count(*) as total_rec';
		return "SELECT ".$cols." FROM ".$this->getTable()." ".$this->buildJoin(). " ".$this->buildWhere($whereQ). " ". $this->buildGroup(). " ". $this->buildSort() ;
	}


	/**
	 * If the dataitem has been loaded, only delete where it's pkey
	 * or combination of uniqs match.
	 * If the dataitem is new, then use the _where settings with optional
	 * where clause tacked on.
	 */
	public function buildDelete($whereQ='') {
		//refuse to delete entire table
		if ( $this->_isNew && $whereQ == '' && count($this->_where) == 0) {
			throw new Exception('Incompatible where clause for delete');
		}

		if ( ! $this->_isNew ) {
			$whereQ = $this->_pkey .' = \''.$this->{$this->_pkey}.'\'';
			if (!isset($this->_pkey) || $this->_pkey === NULL) {
				$whereQ = '';
				$atom = '';
				foreach ($this->_uniqs as $uni) {
					$struct = array('k'=>$uni, 'v'=> $this->get($uni), 's'=>'=', 'andor'=>'and', 'q'=>true);
					$atom = $this->_whereAtomToString($struct, $atom)."\n";
				}
				$whereQ .= $atom;
			}
		}
		return "DELETE FROM ".$this->getTable()." ".$this->buildWhere($whereQ). " " . $this->buildLimit();
	}

	public function buildInsert() {
		$db     = Metrodb_Connector::getHandle(NULL, $this->_table);
		$qc     = $db->qc;
		$vars   = get_object_vars($this);
		$keys   = array_keys($vars);
		$fields = array();

		foreach ($keys as $k) {
			if (substr($k,0,1) == '_') { continue; }
			//fix for SQLITE
			if (isset($this->_pkey) && $k === $this->_pkey && $vars[$k] == NULL ) {continue;}
			$fields[] = $k;
			if ( in_array($k, $this->_bins) ) {
				$values[] = $db->escapeBinaryValue($vars[$k]);//"_binary'".mysql_real_escape_string($vars[$k])."'\n";
			} else if (in_array($k,$this->_nuls) && $vars[$k] == NULL ) {
				//intentionally doing a double equals here,
				// if the col is nullabe, try real hard to insert a NULL
				$values[] = "NULL\n";
			} else {
				//add slashes works just like mysql_real_escape_string
				// (for latin1 and UTF-8) but is faster and testable.
				$values[] = $db->escapeCharValue($vars[$k])."\n";//;"'".addslashes($vars[$k])."'\n";
			}
		}
		//set 'created_on' and 'updated_on' automatically
		//#metadata
		if (!in_array('created_on', $fields)) {
			$fields[] = 'created_on';
			$time = time();
			$values[] = $time;
			$this->set('created_on', $time);
		}
		if (!in_array('updated_on', $fields)) {
			$fields[] = 'updated_on';
			$time = time();
			$values[] = $time;
			$this->set('updated_on', $time);
		}

		return "INSERT INTO ".$this->getTable()." \n".
			' (' . $qc .implode($qc . ",\n" . $qc ,$fields). $qc . ') '."\n".
			'VALUES ('.implode(',',$values).') ';
	}


	/**
	 * Build an entire UPDATE statement for a single row.
	 *
	 * If no primary key (_pkey) is set, then the list of unique columns (uniq)
	 * will be considered unique.
	 */
	public function buildUpdate() {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);
		$qc   = $db->qc;
		$sql = "UPDATE ".$qc.$this->getTable().$qc." SET \n";
		$vars = get_object_vars($this);

		$set = '';

		//remove pkey from update statement
		if (isset($this->_pkey) && $this->_pkey !== NULL) {
			unset($vars[$this->_pkey]);
		}
		//remove object vars that begin with _
		foreach ($vars as $_k=>$_v) {
			if (substr($_k,0,1) == '_') {
				unset($vars[$_k]);
			}
		}


		// auto update updated_on with current timestamp
		//#metadata
		if (!array_key_exists('updated_on', $vars)) {
			$vars['updated_on'] = time();
		}
		$keys = array_keys($vars);

		foreach ($keys as $k) {
			if (strlen($set) ) { $set .= ', ';}
			if ( in_array($k,$this->_bins) ) {
				$set .= $qc.$k.$qc ." = ".$db->escapeBinaryValue($vars[$k])."\n";
			}else if (in_array($k,$this->_nuls) && $vars[$k] == NULL ) {
				$set .= $qc.$k.$qc. " = NULL\n";
			} else {
				$set .= $qc.$k.$qc . " = ".$db->escapeCharValue($vars[$k])."\n";
			}
		}
		$sql .= $set;
		//handle multi-col primary keys
		if (!isset($this->_pkey) || $this->_pkey === NULL) {
			$sql .= ' WHERE ';
			$atom = '';
			foreach ($this->_uniqs as $uni) {
				$struct = array('k'=>$uni, 'v'=> $this->get($uni), 's'=>'=', 'andor'=>'and', 'q'=>true);
				$atom = $this->_whereAtomToString($struct, $atom)."\n";
			}
			//causes problems on sqlite
			//$sql .= $atom .' LIMIT 1';
			$sql .= $atom;
		} else {
			$sql .= ' WHERE '.$this->_pkey .' = \''.$this->{$this->_pkey}.'\'';//.' LIMIT 1';
		}

		return $sql;
	}

	public function buildJoin() {
		$sql = '';
		foreach ($this->_relatedSingle as $_idx => $rel) {
			$tbl = $rel['ftable'];
			$als = $rel['falias'];
			$fk  = $rel['fk'];
			$lk  = $rel['lk'];
			$ltable  = $rel['ltable'];
			$sql .= PHP_EOL.'  LEFT JOIN "'.$tbl.'" AS '.$als.
                    PHP_EOL.'    ON "'.$ltable.'"."'.$lk.'" = "'.$als.'"."'.$fk.'" ';
		}
		return $sql;
	}

	/**
	 * construct a where clause including "WHERE "
	 */
	public function buildWhere($whereQ='') {
		foreach ($this->_where as $struct) {
			$v     = $struct['v'];
			$s     = $struct['s'];
			$k     = $struct['k'];
			$andor = $struct['andor'];
			if (strlen($whereQ) ) {$whereQ .= ' '.$andor.' ';}

			if (isset($struct['subclauses'])) {
				$whereQ .= '(';
			}

			$atom = $this->_whereAtomToString($struct);

			if (isset($struct['subclauses'])) {
				foreach ($struct['subclauses'] as $cl) {
					$atom = $this->_whereAtomToString($cl, $atom);
				}
				$whereQ .= $atom;
				$whereQ .= ')';
			} else {
				$whereQ .= $atom;
			}

		}
		if (strlen($whereQ) ) {$whereQ = ' WHERE '.$whereQ;}
		return $whereQ;
	}

	/**
	 * Convert a where structure into a string, one part at time
	 */
	public function _whereAtomToString($struct, $atom='') {
		$v     = $struct['v'];
		$s     = $struct['s'];
		$k     = $struct['k'];
		$q     = $struct['q'];
		$andor = $struct['andor'];
		if (strlen($atom) ) {$atom .= ' '.$andor.' ';}

		//fix = NULL, change to IS NULL
		//fix != NULL, change to IS NOT NULL
		if ($v === NULL && in_array($k, $this->_nuls)) {
			if ($s == '=') {
				$s = 'IS';
			}
			if ($s == '!=') {
				$s = 'IS NOT';
			}
		}
		$atom .= $k .' '. $s. ' ';

		if (is_string($v) && $v !== 'NULL' && $q) {

			$atom .= '\''.addslashes($v).'\' ';
		} else if ( is_int($v) || is_float($v)) {
			$atom .= $v.' ';
		} else if (is_array($v) && ($s == 'IN' || $s == 'NOT IN')) {
			$atom .= '('.implode(',', $v).') ';
		} else if (substr($v,0,1) == '`') {
			$atom .= $v.' ';
		} else if ($v === 'NULL') {
			$atom .= $v.' ';
		} else if ($v === NULL) {
			$atom .= 'NULL'.' ';
		} else if ($q) {
			$atom .= '\''.addslashes($v).'\' ';
		} else {
			$atom .= ' '.$v. ' ';
		}
		return $atom;
	}

	public function buildSort() {
		if (count($this->_sort) < 1 ) {
			return '';
		}
		$sortQ = '';
		foreach ($this->_sort as $col=>$acdc) {
			if (strlen($sortQ) ) {$sortQ .= ', ';}
			$sortQ .= $col.' '.$acdc;
		}
		return 'ORDER BY '.$sortQ;
	}

	public function buildLimit() {
		/*
		$sortQ = '';
		foreach ($this->_sort as $col=>$acdc) {
			if (strlen($sortQ) ) {$sortQ .= ', ';}
			$sortQ .= ' '.$col.' '.$acdc;
		}
		return $sortQ;
		 */
		if ($this->_limit != -1) {
			return " LIMIT ".($this->_start * $this->_limit).", ".$this->_limit." ";
		}
		return '';
	}

	public function buildGroup() {
		if (count($this->_groupBy) > 0) {
			return " GROUP BY  ".implode(',',$this->_groupBy);
		}
		return '';
	}


	public function andWhere($k,$v,$s='=',$q=TRUE) {
		$this->_where[] = array('k'=>$k,'v'=>$v,'s'=>$s,'andor'=>'and','q'=>$q);
	}

	public function orWhere($k,$v,$s='=',$q=TRUE) {
		$this->_where[] = array('k'=>$k,'v'=>$v,'s'=>$s,'andor'=>'or','q'=>$q);
	}

	public function orWhereSub($k,$v,$s='=',$q=TRUE) {
		$where = array_pop($this->_where);
		$where['subclauses'][] = array('k'=>$k,'v'=>$v,'s'=>$s,'andor'=>'or','q'=>$q);
		$this->_where[] = $where;
	}

	public function andWhereSub($k,$v,$s='=',$q=TRUE) {
		$where = array_pop($this->_where);
		$where['subclauses'][] = array('k'=>$k,'v'=>$v,'s'=>$s,'andor'=>'and','q'=>$q);
		$this->_where[] = $where;
	}

	public function resetWhere() {
		$this->_where = array();
	}

	public function limit($l, $start=0) {
		$this->_limit = $l;
		$this->_start = $start;
	}

	public function sort($col, $acdc='DESC') {
		$this->_sort[$col] = $acdc;
	}

	public function _exclude($col) {
		$this->_excludes[] = $col;
	}

	public function groupBy($col) {
		$this->_groupBy[] = $col;
	}

	public function initBlank() {
		$db = Metrodb_Connector::getHandle(NULL, $this->_table);
		$columns = $db->getTableColumns($this->_table);
		if (!$columns) return;
		foreach ($columns as $_idx => $_col) {
			$this->{$_col['name']} = $_col['def'];
		}
	}

	public function hasManyToMany($table, $alias='', $tableJ='', $tableJLk='', $tableJFk='', $tableFk='') {
		if ($tableJLk == '') { $tableJLk = $this->_pkey;}
		if ($tableJFk == '') { $tableJFk = $table.'_id';}
		if ($tableFk  == '') { $tableFk  = $table.'_id';}
		if ($tableJ   == '') { $tableJ   = $this->_table. '_'.$table.'_link';}

		$aliasJ = 'T'.count($this->_relatedSingle);
		$this->_relatedSingle[] = array(
			'ftable'=>$tableJ,
			'ltable'=>$this->_table,
			'lk'=>$this->_pkey,
			'falias'=>$aliasJ,
			'fk'=>$tableJLk
		);

		if ($alias == '') { $alias = 'T'.count($this->_relatedSingle);}
		//left join  ftable
		// on  ltable.lk = falias.fk
		$this->_relatedSingle[] = array(
			'ftable'=>$table,
			'ltable'=>$aliasJ,
			'lk'=>$tableJFk,
			'falias'=>$alias,
			'fk'=>$tableFk
		);
	}

	/**
	 * Use when the foreign table has its primary key in this object's table.
	 */
	public function hasOne($table, $alias='', $fk = '', $lk = '') {
		if ($alias == '') { $alias = 'T'.count($this->_relatedSingle);}
		if ($fk == '') { $fk = $table.'_id';}
		if ($lk == '') { $lk = $table.'_id'; }
		//left join  ftable
		// on  ltable.lk = falias.fk
		$this->_relatedSingle[] = array(
			'ftable'=>$table,
			'ltable'=>$this->_table,
			'lk'=>$lk,
			'falias'=>$alias,
			'fk'=>$fk
		);
	}

	public function hasMany($table, $alias='', $fk = '', $lk = '') {
		if ($alias == '') { $alias = 'T'.count($this->_relatedSingle);}
		if ($fk == '') { $fk = $this->_table.'_id';}
		if ($lk == '') { $lk = $this->_pkey; }
		//left join  ftable
		// on  ltable.lk = falias.fk
		$this->_relatedSingle[] = array(
			'ftable'=>$table,
			'ltable'=>$this->_table,
			'lk'=>$lk,
			'falias'=>$alias,
			'fk'=>$fk
		);
	}

	public function __toString() {
		$x = "Metrodb_Dataitem [table:".$this->_table."] [id:".sprintf('%d',$this->getPrimaryKey())."] [new:".($this->_isNew?'yes':'no')."]";
		$x .= "\n<br/>\n";
		foreach ($this->valuesAsArray() as $k=>$v) {
			$x .= "$k = $v \n<br/>\n";
		}
		$x .= "\n<hr/>\n";
		return $x;
	}

	/**
	 * Used for debugging
	 */
	public function echoSelect($whereQ='') {
		echo "<pre>\n";
		echo $this->buildSelect($whereQ);
		echo "</pre>\n";
	}

	public function echoDelete($whereQ='') {
		echo "<pre>\n";
		echo $this->buildDelete($whereQ);
		echo "</pre>\n";
	}

	public function echoInsert($whereQ = '') {
		echo "<pre>\n";
		echo $this->buildInsert($whereQ);
		echo "</pre>\n";
	}

	public function echoUpdate($whereQ = '') {
		echo "<pre>\n";
		echo $this->buildUpdate($whereQ);
		echo "</pre>\n";
	}

	/**
	 * Add columns at runtime, or create a missing table.
	 *
	 * @param Object $db  the db connection handle to use
	 * @param bool  $doUpdate whenter or not to call $this->buildInsert() or buildUpdate()
	 */
	public function dynamicReload($db, $whereQ = '') {

		$cols = $db->getTableColumns($this->_table);
		if (!$cols) {
			$sqlDefs = $db->dynamicCreateSql($this);
		} else {
			$sqlDefs = $db->dynamicAlterSql($cols, $this);
		}
		foreach ($sqlDefs as $sql) {
			$db->query($sql);
		}

		return $db->query($this->buildSelect($whereQ));
	}

	/**
	 * Add columns at runtime, or create a missing table.
	 *
	 * @param Object $db  the db connection handle to use
	 * @param bool  $doUpdate whenter or not to call $this->buildInsert() or buildUpdate()
	 */
	public function dynamicResave($db, $doUpdate=FALSE) {

		$cols = $db->getTableColumns($this->_table);
		if (!$cols) {
			$sqlDefs = $db->dynamicCreateSql($this);
		} else {
			$sqlDefs = $db->dynamicAlterSql($cols, $this);
		}
		foreach ($sqlDefs as $sql) {
			$db->query($sql);
		}

		if ($doUpdate) {
			return $db->query($this->buildUpdate());
		}
		return $db->query($this->buildInsert());
	}
}
