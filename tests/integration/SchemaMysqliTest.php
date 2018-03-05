<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../schema.php');
include_once(dirname(__FILE__).'/../../schemamysqli.php');

class Metrodb_Tests_Integration_SchemaMysqli extends PHPUnit_Framework_TestCase { 

	public function setUp() {
		$db = Metrodb_Connector::getHandle('default');
		if (strpos(strtolower(get_class($db)),'mysql') == FALSE) {
			$this->markTestSkipped(
				'The the default db driver is not Mysqli.'
			);
		}
	}

	public function test_get_table_list_returns_array() {
		$schema = new Metrodb_Schema('default', new Metrodb_Schemamysqli() );

		$t = $schema->getTables();
		$this->assertTrue( is_array($t) );
	}

	/*
	public function test_get_table_def() {
		$schema = new Metrodb_Schema('default', new Metrodb_Schemamysqli() );

		$t = $schema->getTable('user_login');
		$this->assertTrue( is_array($t) );
		$this->assertTrue( is_array($t['fields']) );
		$this->assertTrue( is_array($t['indexes']) );
		$this->assertTrue( is_array($t['indexes'][0]['PRIMARY']) );
		$this->assertEquals( $t['table'], 'user_login' );
	}
	 */
}
