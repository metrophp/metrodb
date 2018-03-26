<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../schema.php');

class Metrodb_Tests_Integration_Schema extends PHPUnit_Framework_TestCase { 

	public function setUp() {
	}

	public function test_get_missing_columns_works() {
		$s = new Metrodb_Schema('sqlite3', new Metrodb_Schemasqlite3());


		$di         = new Metrodb_Dataitem('foobar');
		$tableDef   = ['table'=>'foobar', 'fields'=>[
			['name'=>'old_column']
		]];
		$di->old_column = 'foo';
		$di->new_column = 'bar';
		$di->nul_column = 'baz';
		$di->_typeMap['new_column'] = 'lob';
		#should correctly choose string when no type specified
		#$di->_typeMap['nul_column'] = 'string';

		$columnList = $s->getMissingColumns($tableDef, $di);

		$this->assertEquals(
			[[
				'name' => 'new_column',
				'type' => 'longblob',
				'len'  => '',
				'us'   => 0,
				'pk'   => 0,
				'def'  => 'NULL',
				'null' => true,
			],
			[
				'name' => 'nul_column',
				'type' => 'varchar',
				'len'  => '255',
				'us'   => 0,
				'pk'   => 0,
				'def'  => 'NULL',
				'null' => true,
			]],
			$columnList
		);
	}

	public function test_non_integer_pkey() {
		$s = new Metrodb_Schema('sqlite3', new Metrodb_Schemasqlite3());
		$di         = new Metrodb_Dataitem('foobar', 'my_string_pk');
		$di->_typeMap['my_string_pk'] = 'string';
		$tableDef   = $s->makeTableDef($di);

		$this->assertEquals( 'varchar', $tableDef['fields'][0]['type'] );
		$this->assertEquals( 1, $tableDef['fields'][0]['pk'] );
	}
}
