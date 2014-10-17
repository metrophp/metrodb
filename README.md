metrodb
=======
Model first or code first PHP ORM.

Can be used stand-alone or as a library in [nofw](https://github.com/markkimsal/nofw) - the no framework framework.

Usage
=====
Only one line of setup is required to start using metrodb.


Basic Usage and Saving
----
```php
Metrodb_Connector::setDsn('default', 'mysql://root:mysql@localhost/metrodb_test');

$di = new Metrodb_Dataitem('foo');
$di->column1 = 'value_a';
$x = $di->save();
//$x is the pkey value or false on failure
//your database should have a table foo with 4 columns
//foo_id, column1, created_on, updated_on
```

Finding items
----
You can find items and return the results as either a simple array of values or as an arrya of objects (of time Metrodb_Dataitem)

```php
Metrodb_Connector::setDsn('default', 'mysql://root:mysql@localhost/metrodb_test');

$finder = new Metrodb_Dataitem('foo');
$finder->andWhere('column1', 'value_a');
$listAnswer = $finder->findAsArray();
$objsAnswer = $finder->find();
```

Saving items
----
Updating is simply calling save() on any dataitem object.  If an error results where there are missing columns, the object tries to alter the table and re-save (update or insert).  If that second call fails, then an error is returned.

This removes time spent querying the table schema every request when you will probably be adding columns very infrequently vs. reads.

```php
$finder = new Metrodb_Dataitem('foo');
$finder->andWhere('column1', 'value_a');
$finder->_rsltByPkey = false; // we want 0 based result set
$objsAnswer = $finder->find();

$obj = $objsAnswer[0];
$obj->column1 = 'value_b';
$obj->save();
$obj->column2 = 'value_c';
$x = $obj->save();
//x is still the pkey
```
