<?php
#setup metrodb
include_once('dataitem.php');
include_once('connector.php');

$sqliteDsn = 'sqlite3:/:memory:';
$mysqlDsn  = 'mysqli://root:mysql@localhost/metrodb_test_001?ansiQuotes';

Metrodb_Connector::setDsn('sqlite3', $sqliteDsn);
Metrodb_Connector::setDsn('default', $sqliteDsn);

if (function_exists('mysqli_select_db')) {
	Metrodb_Connector::setDsn('mysqli', $mysqlDsn);

	$x  = Metrodb_Connector::getHandleRef('mysqli');
	$y  = $x->exec('create database "metrodb_test_001"');
	if (mysqli_select_db($x->driverId, $x->database) ) {
		$x->isSelected = true;
	}
	//clean up?
	#register_shutdown_function(function(){
	#	$x  = Metrodb_Connector::getHandle('mysqli');
	#	$x->exec('drop database "metrodb_test_001"');
	#});
}
