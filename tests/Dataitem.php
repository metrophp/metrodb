<?php

include_once(dirname(__FILE__).'/../dataitem.php');
include_once('src/nofw/associate.php');

class Metrodb_Tests_Dataitem extends UnitTestCase { 


	public function setUp() {
	}

	public function test_TableNamesandPkey() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$this->assertEqual('foo',       $di->getKind());
		$this->assertEqual('foo_bar',   $di->_pkey);
		$this->assertEqual(NULL,        $di->getPrimaryKey());
	}

	public function test_IsNew() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$this->assertTrue($di->_isNew);
	}

	public function test_EchoSelect() {
		$expectedString = "<pre>\nSELECT * FROM foo   where colA != \"bar\"     LIMIT 125, 25 </pre>\n";
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$di->andWhere('colA', 'bar', '!=');
		$di->limit(25, 5);
		ob_start();
		$di->echoSelect();
		$out = ob_get_contents();
		ob_end_clean();
		$this->assertEqual($expectedString, $out);
	}
}
