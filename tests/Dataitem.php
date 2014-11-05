<?php

include_once(dirname(__FILE__).'/../dataitem.php');

class Metrodb_Tests_Dataitem extends PHPUnit_Framework_TestCase { 


	public function setUp() {
	}

	public function test_TableNamesandPkey() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$this->assertEquals('foo',       $di->getKind());
		$this->assertEquals('foo_bar',   $di->_pkey);
		$this->assertEquals(NULL,        $di->getPrimaryKey());
	}

	public function test_IsNew() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$this->assertTrue($di->_isNew);
	}

	public function test_EchoSelect() {
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
}
