<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../dataitem.php');

class Metrodb_Tests_Integration_Dataitem extends PHPUnit_Framework_TestCase { 


	public function setUp() {
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

	public function test_delete_dataitem() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$di->column1 = 'value_d';
		$x = $di->save();

		$this->assertFalse(!$x);

		$di->delete();

		$finder = new Metrodb_Dataitem('foo', 'foo_bar');
		$finder->andWhere('column1', 'value_d');
		$listAnswer = $finder->findAsArray();

		$this->assertEquals(0, count($listAnswer));
	}

	public function test_update_existing_item() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$di->column1 = 'value_a';
		$x = $di->save();
		$updatedOn = $di->updated_on;

		$this->assertFalse(!$x);

		$di->column1 = 'value_b';
		$x = $di->save();

		$finder = new Metrodb_Dataitem('foo', 'foo_bar');
		$finder->andWhere('column1', 'value_b');
		$listAnswer = $finder->findAsArray();

		$this->assertEquals('value_b', $listAnswer[0]['column1']);
		$this->assertTrue($updatedOn <= $listAnswer[0]['updated_on']);
	}

	public function test_insert_binary() {
		$di = new Metrodb_Dataitem('pictures');

		$di->column1 = "\x00  \"";
		$di->_bins[] = 'column1';
		$x = $di->save();

		//insert
		$this->assertFalse(!$x);

		//update
		$x = $di->save();
		$this->assertFalse(!$x);
	}

	public function tearDown() {
		$db = Metrodb_Connector::getHandle('default');
		$db->truncate('foo');
		$db->truncate('pictures');
	}
}
