<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../schema.php');
include_once(dirname(__FILE__).'/../../schemamysqli.php');

class Metrodb_Tests_Integration_SchemaMysqli extends PHPUnit_Framework_TestCase { 

	public function setUp() {
		$db = Metrodb_Connector::getHandle('mysqli');
		if (strpos(strtolower(get_class($db)),'mysql') == FALSE) {
			$this->markTestSkipped(
				'The the msyqli db driver is not defined or is not Mysqli.'
			);
			return;
		}
		$this->createTables();
	}

	public function createTables() {
		static $ran=0;
		if ($ran) {
			return;
		}
		$ran=1;
		$db = Metrodb_Connector::getHandle('mysqli');
		$y = $db->exec('CREATE TABLE "indextest" ( "indextest_id" INTEGER  PRIMARY KEY AUTO_INCREMENT NOT NULL, "foo" VARCHAR(255) NULL DEFAULT NULL, "bar" VARCHAR(255) NULL DEFAULT NULL) ENGINE = MEMORY');
		$y = $db->exec('CREATE INDEX "idx_indextest" on "indextest" ("foo")');
		$y = $db->exec('CREATE INDEX "idx_indextest2" on "indextest" ("foo", "bar")');
	}

	public function test_get_table_list_returns_array() {
		$schema = new Metrodb_Schema('mysqli', new Metrodb_Schemamysqli() );

		$t = $schema->getTables();
		$this->assertTrue( is_array($t) );
		$this->assertEquals( 1, count($t) );
	}

	public function test_get_table_def_lists_indexes() {
		$schema = new Metrodb_Schema('mysqli', new Metrodb_Schemamysqli() );

		$tableDef = $schema->getTable("indextest");
		$indexes = $tableDef['indexes'];
		$this->assertTrue( is_array($indexes) );
		$this->assertEquals( 3, count($indexes) );
		$this->assertEquals( 2, count($indexes['idx_indextest2']) );
		$this->assertEquals( 'foo', $indexes['idx_indextest2']['column'][0] );
		$this->assertEquals( 'bar', $indexes['idx_indextest2']['column'][1] );
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
