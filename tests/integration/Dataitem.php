<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../dataitem.php');

class Metrodb_Tests_Integration_Dataitem extends PHPUnit_Framework_TestCase { 


	public function setUp() {
		Metrodb_Connector::setDsn('default', 'mysql://root:mysql@localhost/metrodb_test');
	}

	public function test_save_new_dataitem() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$di->column1 = 'value_a';
		$x = $di->save();

		$this->assertFalse(!$x);

		$finder = new Metrodb_Dataitem('foo', 'foo_bar');
		$finder->andWhere('column1', 'value_a');
		$listAnswer = $finder->findAsArray();

		$this->assertEquals('value_a', $listAnswer[0]['column1']);
	}
}
