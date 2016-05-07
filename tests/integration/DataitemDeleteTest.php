<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../dataitem.php');

class Metrodb_Tests_Integration_Dataitem_Delete extends PHPUnit_Framework_TestCase { 


	public function setUp() {
	}


	public function test_delete_dataitem() {
		$di = new Metrodb_Dataitem('del_test', 'foo_bar');

		$di->column1 = 'value_d';
		$x = $di->save();

		$this->assertFalse(!$x);

		$di->delete();

		$finder = new Metrodb_Dataitem('del_test', 'foo_bar');
		$finder->andWhere('column1', 'value_d');
		$listAnswer = $finder->findAsArray();

		$this->assertEquals(0, count($listAnswer));
	}

	public function test_delete_with_new_item_where_param_is_exact() {
		$di = new Metrodb_Dataitem('del_test', 'foo_bar');
		$di->column1 = 'value a';
		$x = $di->save();

		$di = new Metrodb_Dataitem('del_test', 'foo_bar');
		$result = $di->delete('column1=\'value a\'');
		$this->assertTrue( $result );

		$finder = new Metrodb_Dataitem('del_test', 'foo_bar');
		$finder->andWhere('column1', 'value a');
		$results = $finder->findAsArray();

		$this->assertTrue( is_array($results) );
		$this->assertTrue( count($results) == 0 );
	}

	public function test_delete_with_existing_item_param_is_ignored() {

		$remainder = new Metrodb_Dataitem('del_test', 'foo_bar');
		$remainder->column1 = 'value a';
		$remainder->save();

		$di = new Metrodb_Dataitem('del_test', 'foo_bar');
		$di->column1 = 'value b';
		$x = $di->save();
		$result = $di->delete('column1=\'value a\'');
		$this->assertTrue( $result );

		$finder = new Metrodb_Dataitem('del_test', 'foo_bar');
		$finder->andWhere('column1', 'value a');
		$results = $finder->findAsArray();

		$this->assertTrue( is_array($results) );
		$this->assertTrue( count($results) == 1 );

		$finder = new Metrodb_Dataitem('del_test', 'foo_bar');
		$finder->andWhere('column1', 'value b');
		$results = $finder->findAsArray();

		$this->assertTrue( is_array($results) );
		$this->assertTrue( count($results) == 0 );
	}

	public function _makeMultiColumnKeyedDi() {
		$multi = new Metrodb_Dataitem('del_test_multi', NULL);
		$multi->_typeMap['cola'] = 'int';
		$multi->_typeMap['colb'] = 'int';
		$multi->_typeMap['colc'] = 'int';

		$multi->_uniqs[] = 'cola';
		$multi->_uniqs[] = 'colb';

		return $multi;
	}

	public function test_delete_multi_column_primary_key() {

		$di = $this->_makeMultiColumnKeyedDi();
		$di->cola = '1';
		$di->colb = '2';
		$di->colc = '3';
		$x = $di->save();

		$di = $this->_makeMultiColumnKeyedDi();
		$di->cola = '4';
		$di->colb = '5';
		$di->colc = '6';
		$x = $di->save();
		$this->assertTrue( $x !== FALSE );

		$result = $di->delete();
		$this->assertTrue( $result );

		$finder = $this->_makeMultiColumnKeyedDi();
		$finder->andWhere('cola', '1');
		$results = $finder->findAsArray();

		$this->assertTrue( is_array($results) );
		$this->assertTrue( count($results) == 1 );

		$finder = $this->_makeMultiColumnKeyedDi();
		$finder->andWhere('cola', '4');
		$results = $finder->findAsArray();

		$this->assertTrue( is_array($results) );
		$this->assertTrue( count($results) == 0 );
	}

	public static function setupBeforeClass() {
		$db = Metrodb_Connector::getHandle('default');
		$db->exec('CREATE TABLE del_test { foo_bar_id int(11) unsigned auto_increment primary key, column1 varchar(255) default NULL, column2 varchar(255) default NULL )');

	}

	public static function tearDownAfterClass() {
		$db = Metrodb_Connector::getHandle('default');
		$db->exec('DROP TABLE del_test');
		$db->exec('DROP TABLE del_test_multi');
	}
}
