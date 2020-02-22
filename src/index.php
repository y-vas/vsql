<?php

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'VSQL.php');

$_ENV['VSQL_INSPECT'] = true;

$_ENV['DB_HOST'] = '185.224.138.70';
$_ENV['DB_USERNAME'] = 'u345239147_vas';
$_ENV['DB_PASSWORD'] = 'testing';
$_ENV['DB_DATABASE'] = 'u345239147_datab';

use VSQL\VSQL\VSQL;

$v = new VSQL();

// $v->model('tba');

$v->query("
select * from tba
", array(
  'name' => 0.25,
  'ray' => ["1",'vas','vas']
));

$res = $v->get(true);
var_dump($res);
