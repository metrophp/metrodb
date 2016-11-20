<?php

include_once(dirname(__FILE__).'/../../dataitem.php');
include_once(dirname(__FILE__).'/../../modeltrait.php');

class Metrodb_Tests_Modeltrait extends PHPUnit_Framework_TestCase { 

	public function setUp() {
		$this->traittester = new TraitTester();
	}

	/**
	 * 
	 */
	public function test_always_decamelize_case() {
		$this->traittester->fooBar = 3;
		$di = $this->traittester->dataitem;
		$this->assertEquals(3, $di->foo_bar);
	}

	/**
	 * 
	 */
	public function test_can_change_existing_camel_case_members() {
		$this->traittester->_relatedMany = 3;
		$di = $this->traittester->dataitem;
		$this->assertEquals(3, $di->_relatedMany);
	}

	/**
	 *
	 */
	public function test_can_get_existing_camel_case_members() {
		$this->traittester->_relatedMany = 3;
		$this->assertEquals(3, $this->traittester->_relatedMany);
	}

	public function test_turn_off_case_switching_feature() {
		$nochange = new TraitNoCaseChange();
		$nochange->_relatedMany = 3;
		$nochange->fooBar = 4;
		$this->assertEquals(3, $nochange->_relatedMany);
		$this->assertEquals(4, $nochange->dataitem->fooBar);
	}

}

class TraitTester {
	use \Metrodb_Modeltrait;
	public $tableName = 'trait_tester';

	public function makeDataItem() {
		return new Metrodb_Dataitem($this->tableName);
	}
}

class TraitNoCaseChange {
	public $tableName = 'trait_tester';
	public $skipCaseChange = TRUE;

	use \Metrodb_Modeltrait;

	public function makeDataItem() {
		return new Metrodb_Dataitem($this->tableName);
	}
}
