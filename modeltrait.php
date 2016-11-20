<?php

/**
 * This trait passes through access to undefined members to the 
 * internal dataitem.  It will translate camelCase to snake_case
 * UNLESS you define a property public $skipCaseChange = TRUE;
 */
trait Metrodb_Modeltrait {

	public $dataitem   = NULL;

	public function __construct($dataitem=NULL) {
		if($dataitem === NULL) {
			$dataitem = $this->makeDataItem();
		}
		$this->dataitem = $dataitem;
	}

	/**
	 *
	 */
	public function makeDataItem() {
		#\_make('dataitem', self::$tableName);
		return \_makeNew('dataitem', $this->tableName);
	}

	/**
	 *
	 */
	public function load($id) {
		$x = $this->dataitem->load($id);
		$this->postLoad();
		return $x;
	}

	/**
	 *
	 */
	public function loadExisting() {
		$x = $this->dataitem->loadExisting();
		$this->postLoad();
		return $x;
	}


	public function postLoad() {
	}

	/**
	 *
	 */
	public function save() {
		if ($this->preSave()) {
			return $this->dataitem->save();
		}
		return FALSE;
	}

	/**
	 *
	 */
	public function preSave() {
		return TRUE;
	}

	/**
	 *
	 */
	public function delete() {
		return $this->dataitem->delete();
	}

	public function __isset($key) {
		if (!@$this->skipCaseChange) {
			$lowk = $this->decamelize($key);
			if (array_key_exists($lowk, $this->dataitem->_typeMap)) {
				return TRUE;
			}
			if (isset($this->dataitem->{$lowk})) {
				return TRUE;
			}
		}
		return isset($this->dataitem->{$key});
	}

	public function __unset($key) {
		if (!@$this->skipCaseChange) {
			$lowk = $this->decamelize($key);
			if (array_key_exists($lowk, $this->dataitem->_typeMap)) {
				unset($this->dataitem->{$lowk});
			}
			if (isset($this->dataitem->{$lowk})) {
				unset($this->dataitem->{$lowk});
			}
		}
		unset($this->dataitem->{$key});
	}

	/**
	 * If this key exists on the data item already, update it and return
	 * If not, try to switch camelCase to snake_case.
	 * If $object->skipChangeCase == true don't munge the key at all
	 */
	public function __set($key, $val) {
		if (isset($this->dataitem->{$key})) {
			$this->dataitem->{$key} = $val;
			return;
		}

		if (!@$this->skipCaseChange) {
			$key = $this->decamelize($key);
			/*
			if (array_key_exists($lowk, $this->dataitem->_typeMap)) {
				$this->dataitem->{$lowk} = $val;
				return;
			}
			 */
		}
		$this->dataitem->{$key} = $val;
	}

	public function set($k, $v) {
		$this->dataitem->{$k} = $v;
	}


	public function __get($k) {
		if (!@$this->skipCaseChange) {
			$lowk = $this->decamelize($k);
			if (array_key_exists($lowk, $this->dataitem->_typeMap)) {
				return $this->dataitem->{$lowk};
			}
			if (isset($this->dataitem->{$lowk})) {
				return $this->dataitem->{$lowk};
			}
		}

		if (isset($this->dataitem->{$k})) {
			return $this->dataitem->{$k};
		}

		return NULL;
	}


	public function get($k, $d=NULL) {
		if (isset($this->dataitem->{$k})) {
			return $this->dataitem->{$k};
		}
		return $d;
	}

	public function getPrimaryKey() {
		return $this->dataitem->getPrimaryKey();
	}

	public function __call($func, $args) {
		if (substr($func, 0, 3) == 'get') {
			if (!@$this->skipCaseChange) {
				array_unshift($args, $this->decamelize(substr($func,3)));
			} else {
				array_unshift($args, $this->decamelize(substr($func,3)));
			}
			return call_user_func_array(array($this->dataitem, 'get'), $args);
		}
		if (substr($func, 0, 3) == 'set') {
			if (!@$this->skipCaseChange) {
				array_unshift($args, $this->decamelize(substr($func,3)));
			} else {
				array_unshift($args, $this->decamelize(substr($func,3)));
			}
			return call_user_func_array(array($this->dataitem, 'set'), $args);
		}
		return call_user_func_array(array($this->dataitem, $func), $args);
	}

	public function __toString() {
		return $this->dataitem->__toString();
	}

	public function decamelize($string) {
		    return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
	}
}
