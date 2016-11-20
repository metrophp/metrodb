<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../dataitem.php');

class Metrodb_Tests_Integration_Sqlite3 extends PHPUnit_Framework_TestCase { 


	public function setUp() {
		$this->db = Metrodb_Connector::getHandle('sqlite3');
	}


	public function test_get_last_insert_id() {
		$this->db->exec('CREATE TABLE "insert_id_test" ( "id" int(11) PRIMARY KEY, "name" varchar(255) );');

		$this->db->exec('insert into "insert_id_test" ("name") values(\'bar\')');
		$id = $this->db->getInsertID();
		$this->assertEquals(1, $id);
	}

	public function tearDown() {
		$this->db->exec('DROP TABLE "insert_id_test"');
	}
}
