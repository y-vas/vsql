<?php
include('Mold.php');


$_ENV['VSQL_INSPECT'] = true;
$_ENV[  'DB_HOST'  ] = '172.19.0.2';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = 'root';
$_ENV['DB_DATABASE'] = 'dbtest';


$m = new Mold();
$m->makeMold('Actius');
