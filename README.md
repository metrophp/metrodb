metrodb
=======
Model first or code first PHP ORM.

Can be used stand-alone or as a library in [Metro PHP](https://github.com/metrophp).

Installation with composer
=====
This project is not on packagist, so you have to add a repository (because composer can't guess it correctly?)

```
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/metrophp/metrodb.git"
        }
    ],
```
Then add the dependency as you normally would.

```
    "require": {
        "php": ">= 5.3.7",
        "metrodb": ">=1.4.0"
    },
```



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

Relations
----
3 relations are supported:

  * Single Relation
  * Many Relation
  * Many to Many Relation

Use ```$obj->hasOne('address')``` when your table has one column that matches one ID to another table.

```
| obj_id | name | address_id |
| 1      | x    | 1          |
| 3      | x    | 99         |

| address_id | street | zip  |
| 1          | elm    | 1234 |
| 99         | oak    | 4321 |
```


Use ```$obj->hasMany('address')``` when the __related table__ has one column that matches the ID in your obj table.

```
| obj_id | name |
| 1      | x    |
| 3      | x    |

| address_id | street | zip  | obj_id |
| 1          | elm    | 1234 | 1      |
| 99         | oak    | 4321 | 1      |
```




Use ```$obj->hasManyToMany('address')``` when there exists a __join table__ which has columns for both tables' ID
le.

```
| obj_id | name |
| 1      | x    |
| 3      | x    |

| obj_address_link_id | address_id | obj_id | rank |
| 1                   | 1          | 1      | 1    |
| 2                   | 99         | 1      | 2    |
| 3                   | 1          | 3      | 1    |

| address_id | street | zip  |
| 1          | elm    | 1234 |
| 99         | oak    | 4321 |
```
