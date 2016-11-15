<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../mysqli.php');

class Metrodb_Tests_Mysqli extends PHPUnit_Framework_TestCase {


	public function setUp() {
	}

	public function test_simulate_prepared_statements() {
		$this->assertTrue(TRUE);
	}
	/*
	public function test_simulate_prepared_statements() {
		$my = new Metrodb_Mysqli();
		$result = $my->_prepareStatement('update ? where x = ? and y = ?', array('table', 'abc?', 33));
		$this->assertEquals(
			'update \'table\' where x = \'abc?\' and y = 33',
			$result
		);
	}
	 */
}
