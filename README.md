# VSQL

// site
https://ymvas.com/vsql


VSQL is a query helper and abstraction layer for php.

### COMPOSER INSTALATION
```sh
composer require vasyl/vsql
```

### Basic Script

````php
use VSQL\VSQL\VSQL;

// set this to true if you are on debug mode
$_ENV['VSQL_INSPECT'] = true;

// declare the database variables in ENV
$_ENV[  'DB_HOST'  ] = 'host';
$_ENV['DB_USERNAME'] = 'name';
$_ENV['DB_PASSWORD'] = 'pass';
$_ENV['DB_DATABASE'] = 'dtbs';

$v = new VSQL( );
$query = $v->query(
   " SELECT * FROM Table T
     WHERE TRUE
    { AND T.name = :name } "
  ,[ 'name' => 'vsql' ]
  , true  
);

// what query will return
/*
  $query
  "
  SELECT * FROM Table T
  WHERE TRUE
  AND T.name = 'vsql'
  "
*/

$res = $v->get( $list = true );
//returns a standart class object

// if you want to return the mysqli instance run this instead
// $mysqli = $v->run( $list = true );

````

#### Handeling Big queries is now easy

Given this values and this query
````php
$values = [
  'name'     => 'vsql',
  'getbasic' => true,
  'pass'     => 'secret'
]
````

Givent Query
````sql
SELECT
  :name
  { , d.name :extra_cols }
  { , d.name ,d.surname, d.pass  getbasic; }
  , d.id
FROM dbtable d
WHERE TRUE
AND d.surname like '%{:surname}%'
{ AND d.type = i:type }
{ AND d.pass = s:pswd }
{ AND d.id   = i:id   }
````

Output Query
````sql
SELECT
  vsql
  , d.name ,d.surname, d.pass
  , d.id
FROM dbtable d
WHERE TRUE
AND d.surname like '%%'
AND d.pass = 'secret'
````

### Transformers

|   transformer &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; |variables &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;|returns   &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;                     |
|----------------|-------------------------------|-------------------------------|
|       i        |    'string',0 ,'123.3', null  |    0,0 ,123,   0              |
|      +i        |    'string',0 ,'-123.3', -2   |    0,0 ,123, 2                |
|       f        |    'string',0 ,'123.3', null  |    0,0 ,123.3, 0              |
|      +f        | -3, -1.3  ,0 ,'123.3' , null  |   3, 1.3 ,0 ,123.3, 0         |
|       s        |    'string',0 ,'123.3', null  |    'string','0','123.3',''    |
|       t        | '  string  ',0 ,'123.3', null |    'string','0','123.3',''    |
| array/implode  |  ['string',0 ,'123.3', null]  |    'string,0,123.3,'          |
|      json      |  ['string',0 ,'123.3', null]  |'[\"string\",0,\"123.3\",null]'|


### Classes
- VSQL
  - Query Compiler ```php $db->query('select * from dbtable',array()); ```
  - Fetch Rows ```php $db->get( $list = false ); ```
  - Execute ```php $db->run(  $list = false ); /* retuns mysql instance */```
