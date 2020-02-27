# VSQL

VSQL is a simple query helper and abstraction layer for php.

### COMPOSER INSTALATION
```sh
composer require vasyl/vsql
```

### Introduction

````php
use VSQL\VSQL\VSQL;

// set this to true if you are testing or debugging
// it will show the difference between queries

$_ENV['VSQL_INSPECT'] = true;

// declare the database variables en ENV
$_ENV[  'DB_HOST'  ] = 'host';
$_ENV['DB_USERNAME'] = 'name';
$_ENV['DB_PASSWORD'] = 'pass';
$_ENV['DB_DATABASE'] = 'dtbs';

$v = new VSQL( );

// this is the vsql syntax
$query = $v->query("
SELECT
  *
FROM dbtable d
WHERE TRUE
{ AND d.name = :name }
  ", array(
  'name'=> 'vsql'
), true /*if true it will die and show the debug */ );

````
#### Return query
````sql
SELECT
  *
FROM dbtable d
WHERE TRUE
AND d.name = 'vsql'
````

````php
$res = $v->get( true /* if true it will fetch all rows else only 1 */ );
//get returns a standart class object

````

#### Query Syntax

Our Table
|id  |name    |surname    | pass | type   |
|----|--------|-----------|------|--------|
|1   |vas     |yv         | vsql | admin  |
|2   |bah     |md         | dotr | client |
|3   |john    |doe        | 545d | client |
|4   |max     |power      | 0212 | client |

````php
$values = [
  'name'=>'vsql',
  'query2'=> true,
  'col2'=>'d.type'
]
````

````sql
SELECT
  /* if the value name is not empty the :field will appear */
  :name
  /* result:  'vsql'   */

  /* if the value extracol is not empty the :field will appear
    with all content delimited by { } without the brackets
    else all content delimited by { } will be removed */
  { , d.name :extracol }
  /* result:     */

  /*if you want use the brackets as they are use \{ and \}*/

  /* if the value query2 is not empty the field; will **NOT** appear
    with all content delimited by { } without the brackets
    else all content delimited by { } will be removed */
  { , d.name ,d.surname, d.pass  query2; }
  /* result:   , d.name ,d.surname, d.pass  */

  , d.id
FROM dbtable d
WHERE TRUE
/* if ? is added after the :field and this is empty it will use ( vs ) as default */
AND d.surname like '%{:surname ? vs}%'
/* result:   AND d.surname like '%vs%'  */

/* if ! is added after the :field and this is empty it will trow an exception */
AND d.type = :type !

/* if [s,i,f] is added before the :field it will transform it befere parsing it */
AND d.pass = s:pswd

/* combine to handdle errors  */
AND d.id = i:pswd ? 0
````

### Transformers
|   transformer  |variables                      |returns                        |
|----------------|-------------------------------|-------------------------------|
|       i        |    'string',0 ,'123.3', null  |    0,0 ,123,   0              |
|       f        |    'string',0 ,'123.3', null  |    0,0 ,123.3, 0              |
|       s        |    'string',0 ,'123.3', null  |    'string','0','123.3',''    |
|       t        | '  string  ',0 ,'123.3', null |    'string','0','123.3',''    |
| array/implode  |  ['string',0 ,'123.3', null]  |    'string,0,123.3,'          |
|      json      |  ['string',0 ,'123.3', null]  |'[\"string\",0,\"123.3\",null]'|


### Classes
- DB
  - Connection ```php $db->connect(); ```
  - Model Maker ```php $db->model('dbtable'); ```
  - Cache ```php $db->chquery(); ``` **NOT USED**

- VSQL
  - Query Compiler ```php $db->query('select * from dbtable',array()); ```
  - Fetch Rows ```php $db->get( $list = false ); ```
  - Execute ```php $db->run(); /* retuns mysql instance */```
