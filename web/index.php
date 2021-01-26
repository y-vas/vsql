<?php
require_once '../src/VSQL.php';
use VSQL\VSQL\VSQL;

$_ENV['VSQL_INSPECT'] = true;
$_ENV[  'DB_HOST'  ] = '172.29.0.2';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = 'root';
$_ENV['DB_DATABASE'] = 'dbtest';

$vsql = new VSQL();
echo "string";
