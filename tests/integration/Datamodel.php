<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../datamodel.php');

class Test_Datamodel extends Metrodb_Datamodel {
	public $tableName = 'baz';
}

class Metrodb_Tests_Integration_Datamodel extends PHPUnit_Framework_TestCase {


	public function setUp() {
		Metrodb_Connector::setDsn('default', 'mysql://docker:mysql@192.168.2.65:3309/metrodb_test');
	}

	public function test_save_new_dataitem() {
		$dm = new Test_Datamodel();

		$dm->column1 = 'value_a';
		$x = $dm->save();

		$this->assertFalse(!$x);

		$dm2 = new Test_Datamodel($x);
		$this->assertFalse($dm2->dataItem->_isNew);
		$this->assertEquals('value_a', $dm2->column1);
	}

	public function tearDown() {
		$db = Metrodb_Connector::getHandle('default');
		$db->execute('TRUNCATE `baz`');
	}
}
