<?php

include_once(dirname(__FILE__).'/../connector.php');

class Metrodb_Tests_Connector extends PHPUnit_Framework_TestCase {


	public function setUp() {
	}

	public function test_get_dsn() {
		$url = 'mysql://foo@bar/dbname';
		Metrodb_Connector::setDsn('default', $url);
		$url2 = Metrodb_Connector::getDsn('default');
		$this->assertEquals($url, $url2);

		$url3 = Metrodb_Connector::getDsn('write');
		$this->assertEquals(NULL, $url3);
	}

	public function test_get_driver() {
		$url = 'mysql://foo@bar/dbname';
		Metrodb_Connector::setDsn('default', $url);
		$db = Metrodb_Connector::getHandle('default');
		$this->assertTrue(is_object($db));
		$this->assertEquals('metrodb_mysql', get_class($db));
	}
}
