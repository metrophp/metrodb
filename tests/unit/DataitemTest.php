<?php

include_once(dirname(__FILE__).'/../../dataitem.php');

class Metrodb_Tests_Dataitem extends PHPUnit_Framework_TestCase { 


	public function setUp() {
	}

	public function test_table_names_and_pkey() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$this->assertEquals('foo',       $di->getKind());
		$this->assertEquals('foo_bar',   $di->_pkey);
		$this->assertEquals(NULL,        $di->getPrimaryKey());
	}

	public function test_is_new() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$this->assertTrue($di->_isNew);
	}

	public function test_echo_select() {
		$expectedString = "<pre>\nSELECT * FROM foo   where colA != 'bar'     LIMIT 125, 25 </pre>\n";
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$di->andWhere('colA', 'bar', '!=');
		$di->limit(25, 5);
		ob_start();
		$di->echoSelect();
		$out = ob_get_contents();
		ob_end_clean();
		$this->assertEquals($expectedString, $out);
	}


	public function test_get_values_as_array() {
		$di = new Metrodb_Dataitem('foo');
		$di->set('colA', 90);
		$di->colB =  'abc';

		$expected = array(
			'colA'=> 90,
			'colB' => 'abc',
			'foo_id' => NULL
		);
		$values = $di->valuesAsArray();
		$this->assertEquals($expected, $values);
	}
}