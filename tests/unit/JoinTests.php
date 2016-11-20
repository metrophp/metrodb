<?php
include_once(dirname(__FILE__).'/../../dataitem.php');

class Metrodb_Tests_Join extends PHPUnit_Framework_TestCase {


	public function setUp() {
	}

	public function test_join_many_to_many() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$di->hasManyToMany('foo_option', 'foo_option_link');

$expected = '
  LEFT JOIN "foo_option_link" AS T0
    ON "foo"."foo_bar" = "T0"."foo_bar" 
  LEFT JOIN "foo_option" AS T1
    ON "T0"."foo_option_id" = "T1"."foo_option_id" ';

		$joins = $di->buildJoin();
		$this->assertEquals( $expected, $joins );
	}

	public function test_join_single_relationship() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$di->hasOne('foo_option');

$expected = '
  LEFT JOIN "foo_option" AS T0
    ON "foo"."foo_option_id" = "T0"."foo_option_id" ';

		$joins = $di->buildJoin();
		$this->assertEquals( $expected, $joins );
	}


	public function test_join_single_relationship_with_custom_keys() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$di->hasOne('foo_option', 'id');

$expected = '
  LEFT JOIN "foo_option" AS T0
    ON "foo"."foo_option_id" = "T0"."id" ';

		$joins = $di->buildJoin();
		$this->assertEquals( $expected, $joins );
	}



	public function test_join_many_relationship() {
		$di = new Metrodb_Dataitem('foo', 'foo_bar');

		$di->hasMany('foo_option');

$expected = '
  LEFT JOIN "foo_option" AS T0
    ON "foo"."foo_bar" = "T0"."foo_id" ';

		$joins = $di->buildJoin();
		$this->assertEquals( $expected, $joins );
	}


	public function test_join_many_relationship_with_custom_pkey() {
		$di = new Metrodb_Dataitem('foo', 'id');

		$di->hasMany('foo_option', 'foo_bar_id');

$expected = '
  LEFT JOIN "foo_option" AS T0
    ON "foo"."id" = "T0"."foo_bar_id" ';

		$joins = $di->buildJoin();
		$this->assertEquals( $expected, $joins );
	}
}
