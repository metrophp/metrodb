<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../mysql.php');

class Metrodb_Tests_Mysql extends PHPUnit_Framework_TestCase { 


	public function setUp() {
	}

	public function test_simulate_prepared_statements() {
		$my = new Metrodb_Mysql();
		$result = $my->_prepareStatement('update ? where x = ? and y = ?', array('table', 'abc?', 33));
		$this->assertEquals(
			'update \'table\' where x = \'abc?\' and y = 33',
			$result
		);
	}
}
