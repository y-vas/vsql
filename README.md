# VSQL

VSQL is a simple query helper and abstraction layer for php.

### COMPOSER INSTALATION
```sh
composer require vasyl/vsql
```

### Introduction

````php
use VSQL\VSQL\VSQL;

// set this to true if you are testing or debuging
// it will show the diference betwen queryes

$_ENV['VSQL_INSPECT'] = true;

// declate the database variables en ENV
$_ENV[  'DB_HOST'  ] = 'host';
$_ENV['DB_USERNAME'] = 'name';
$_ENV['DB_PASSWORD'] = 'pass';
$_ENV['DB_DATABASE'] = 'dtbs';

$v = new VSQL( );

// example
$v->query("SELECT
  *
  FROM dbtable d
  WHERE TRUE
  { AND d.name = :name }
  ", array(
  'name'=> 'vsql'
));

$res = $v->get( true );

````


### Parsed Values
|                |ASCII                          |HTML                         |
|----------------|-------------------------------|-----------------------------|
|Single backticks|`'Isn't this fun?'`            |'Isn't this fun?'            |
|Quotes          |`"Isn't this fun?"`            |"Isn't this fun?"            |
|Dashes          |`-- is en-dash, --- is em-dash`|-- is en-dash, --- is em-dash|


### Classes
- DB
  - Connection ```php $db->connect(); ```
  - Model Maker ```php $db->model('dbtable'); ```
  - Cache ```php $db->chquery(); ``` **NOT USED**

- VSQL
  - Query Compiler ```php $db->query('select * from dbtable',array()); ```
  - Fetch Rows ```php $db->get( $list = false ); ```
  - Execute ```php $db->run(); /* retuns mysql instance */```
