<?php
#setup metrodb
include_once('dataitem.php');
include_once('connector.php');

Metrodb_Connector::setDsn('default', 'mysql://root:mysql@localhost/metrodb_test');
