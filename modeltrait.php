<?php

trait Metrodb_Modeltrait {

	public $dataitem  = NULL;

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
		if ($x) {
			$this->hydrate();
		}
		return $x;
	}

	/**
	 *
	 */
	public function loadExisting() {
		$x = $this->dataitem->loadExisting();
		if ($x) {
			$this->hydrate();
		}
		return $x;
	}


	public function hydrate() {
	}

	/**
	 *
	 */
	public function save() {
		return $this->dataitem->save();
	}

	/**
	 *
	 */
	public function delete() {
		return $this->dataitem->delete();
	}

	public function set($k, $v) {
		$this->dataitem->{$k} = $v;
	}

	public function get($k, $d=NULL) {
		if (!isset($this->dataitem->{$k})) {
			return $d;
		}
		return $this->dataitem->{$k};
	}

	public function __call($func, $args) {
		return call_user_func_array(array($this->dataitem, $func), $args);
	}

	public function __toString() {
		return $this->dataitem->__toString();
	}
}
