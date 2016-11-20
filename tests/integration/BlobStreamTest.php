<?php

include_once(dirname(__FILE__).'/../../connector.php');
include_once(dirname(__FILE__).'/../../blobstream.php');

class Metrodb_Tests_Integration_Blobstream extends PHPUnit_Framework_TestCase { 


	public function setUp() {
		$this->sqlite  = Metrodb_Connector::getHandle('sqlite3');
		$this->default = Metrodb_Connector::getHandle('default');
	}


	public function test_stream_returns_valid_data() {
		//20 bytes of binary data
		$binaryData   = pack("c*", 0x12, 0x34, 0x56, 0x78, 65);
		$binaryData  .= pack("c*", 0x12, 0x34, 0x56, 0x78, 65);
		$binaryData  .= pack("c*", 0x12, 0x34, 0x56, 0x78, 65);
		$binaryData  .= pack("c*", 0x12, 0x34, 0x56, 0x78, 65);
		$expectedHash = md5($binaryData);
		$id = 1;

		$this->default->exec('CREATE TABLE "blobstream_test" ( "id" int(11) PRIMARY KEY, "binvalue" BLOB );');
		$this->default->exec('INSERT INTO "blobstream_test" ("id", "binvalue") values('.$id.', '.$this->default->escapeBinaryValue($binaryData).')');
		$resultData = NULL;
		$blobstream = new Metrodb_Blobstream($this->default, 'blobstream_test', 'binvalue', $id, 10, 'id');
		$counter = 0;
		while ($data = $blobstream->read($this->default) ) {
			$resultData .= $data;
			$counter++;
		}

		$this->assertEquals($expectedHash, md5($resultData));
		$this->assertEquals($resultData, $binaryData);
		$this->assertEquals(10, $counter);
	}

	public function test_odd_size_returns_extra_chars_in_last_chunk() {
		//20 bytes of binary data
		$binaryData   = pack("c*", 0x12, 0x34, 0x56, 0x78, 65);
		$binaryData  .= pack("c*", 0x12, 0x34, 0x56, 0x78, 65);
		$binaryData  .= pack("c*", 0x12);
		$expectedHash = md5($binaryData);
		$id = 2;

		$this->default->exec('CREATE TABLE "blobstream_test" ( "id" int(11) PRIMARY KEY, "binvalue" BLOB );');
		$this->default->exec('INSERT INTO "blobstream_test" ("id", "binvalue") values('.$id.', '.$this->default->escapeBinaryValue($binaryData).')');
		$resultData = NULL;
		$blobstream = new Metrodb_Blobstream($this->default, 'blobstream_test', 'binvalue', $id, 10, 'id');
		$counter = 0;
		while ($data = $blobstream->read($this->default) ) {
			$resultData .= $data;
			$counter++;
		}

		$this->assertEquals($expectedHash, md5($resultData));
		$this->assertEquals(10, $counter);
	}

	public function tearDown() {
		$this->default->exec('DROP TABLE "blobstream_test"');
	}
}
