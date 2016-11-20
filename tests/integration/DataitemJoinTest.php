<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../dataitem.php');

class Metrodb_Tests_Integration_Dataitem_Join extends PHPUnit_Framework_TestCase { 


	public function setUp() {
	}


	public function test_join_single() {

		$child  = new Metrodb_Dataitem('child');
		$child->set('title', 'test child');
		$child->set('parent_id', 1);
		$child->save();


		$child  = new Metrodb_Dataitem('child');
		$child->set('title', 'test child 2');
		$child->set('parent_id', 2);
		$child->save();

		$parent = new Metrodb_Dataitem('parent');
		$parent->set('title', 'test parent');
		$parent->save();

		$parent = new Metrodb_Dataitem('parent');
		$parent->set('title', 'test parent 2');
		$parent->save();
		/////////////////////

		$finder = new Metrodb_Dataitem('child');
		$finder->_cols[] = 'child.*';
		$finder->hasOne('parent');
		$finder->andWhere('child_id', 1);
		$result = $finder->findAsArray();

		$this->assertEquals(1, count($result));
		$this->assertEquals('test child', $result[0]['title']);
	}

	public function test_join_single_reverse() {

		$child  = new Metrodb_Dataitem('child');
		$child->set('title', 'test child');
		$child->set('parent_id', 1);
		$child->save();

		$child  = new Metrodb_Dataitem('child');
		$child->set('title', 'test child 2');
		$child->set('parent_id', 2);
		$child->save();

		$parent = new Metrodb_Dataitem('parent');
		$parent->set('title', 'test parent');
		$parent->save();

		$parent = new Metrodb_Dataitem('parent');
		$parent->set('title', 'test parent 2');
		$parent->save();
		/////////////////////

		$finder = new Metrodb_Dataitem('parent');
		$finder->_cols[] = 'parent.*, TC.child_id';
		$finder->hasOne('child', 'parent_id', 'parent_id', 'TC');
		$finder->andWhere('parent.parent_id', 2);
		//echo $finder->echoSelect();
		$result = $finder->findAsArray();

		$this->assertEquals(1, count($result));
		$this->assertEquals('test parent 2', $result[0]['title']);
		$this->assertEquals(2, $result[0]['child_id']);
		$this->assertEquals(2, $result[0]['parent_id']);
	}

	public function test_join_many_to_many() {

		$child  = new Metrodb_Dataitem('child');
		$child->set('title', 'test child');
		$child->save();

		$child  = new Metrodb_Dataitem('child');
		$child->set('title', 'test child 2');
		$child->save();

		$parent = new Metrodb_Dataitem('parent');
		$parent->set('title', 'test parent');
		$parent->save();

		$parent = new Metrodb_Dataitem('parent');
		$parent->set('title', 'test parent 2');
		$parent->save();


		$rel    = new Metrodb_Dataitem('parent_child_link');
		$rel->set('child_id', 1);
		$rel->set('parent_id', 1);
		$rel->save();

		$rel    = new Metrodb_Dataitem('parent_child_link');
		$rel->set('child_id', 2);
		$rel->set('parent_id', 1);
		$rel->save();

		$rel    = new Metrodb_Dataitem('parent_child_link');
		$rel->set('child_id', 2);
		$rel->set('parent_id', 2);
		$rel->save();
		/////////////////////

		$finder = new Metrodb_Dataitem('parent');
		$finder->_cols[] = 'T1.title';
		$finder->_cols[] = 'T0.child_id';
		$finder->hasManyToMany('child');
		$finder->andWhere('parent.parent_id', 1);
		//$finder->echoSelect();
		$result = $finder->findAsArray();

		$this->assertEquals(2, count($result));
		$this->assertEquals('test child', $result[0]['title']);
		$this->assertEquals('test child 2', $result[1]['title']);
	}

	public function tearDown() {
		$db = Metrodb_Connector::getHandleRef('default');

		$db->truncate('parent');
		$db->truncate('child');
		$db->truncate('parent_child_link');
	}
}
