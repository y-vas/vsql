<?php
require_once '../src/VSQL.php';
use VSQL\VSQL\VSQL;

$_ENV['VSQL_INSPECT'] = true;
$_ENV[  'DB_HOST'  ] = 'mysql_vsql';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = 'root';
$_ENV['DB_DATABASE'] = 'dbtest';

$vsql = new VSQL();
$vsql->query("SELECT * FROM Db",[] , true );
echo "string";
