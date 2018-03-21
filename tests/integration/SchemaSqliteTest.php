<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../schema.php');
include_once(dirname(__FILE__).'/../../schemasqlite3.php');

class Metrodb_Tests_Integration_SchemaSqlite extends PHPUnit_Framework_TestCase { 

	public function setUp() {
		$db = Metrodb_Connector::getHandle('sqlite3');
		if (strpos(strtolower(get_class($db)), 'sqlite') == FALSE) {
			$this->markTestSkipped(
				'The the default db driver is not Sqlite3.'
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
		$db = Metrodb_Connector::getHandle('sqlite3');
		$y = $db->exec('CREATE TABLE "indextest" (
			"indextest_id" INTEGER  PRIMARY KEY AUTOINCREMENT NOT NULL,
			"foo" VARCHAR(255) NULL DEFAULT NULL,
			"bar" VARCHAR(255) NULL DEFAULT NULL)');
		$y = $db->exec('CREATE INDEX "idx_indextest" on "indextest" ("foo")');
		$y = $db->exec('CREATE INDEX "idx_indextest2" on "indextest" ("foo", "bar")');
	}

	public function test_get_table_list_returns_array() {
		$schema = new Metrodb_Schema('sqlite3', new Metrodb_Schemasqlite3() );

		$t = $schema->getTables();
		$this->assertTrue( is_array($t) );
		//always get "sqlite_sequence" and "indextest"
		$this->assertEquals( 2, count($t) );
	}

	public function test_get_table_def_lists_indexes() {
		$schema = new Metrodb_Schema('sqlite3', new Metrodb_Schemasqlite3() );

		$tableDef = $schema->getTable("indextest");
		$indexes = $tableDef['indexes'];
		$this->assertTrue( is_array($indexes) );
		//doesn't include PRIMARY KEY in indexes
		$this->assertEquals( 2, count($indexes) );
		$this->assertEquals( 2, count($indexes['idx_indextest2']) );
		$this->assertEquals( 'foo', $indexes['idx_indextest2']['column'][0] );
		$this->assertEquals( 'bar', $indexes['idx_indextest2']['column'][1] );
	}
}
