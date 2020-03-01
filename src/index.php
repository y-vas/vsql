<?php

require(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Mold.php');

$_ENV['VSQL_INSPECT'] = true;
$_ENV[  'DB_HOST'  ] = '185.224.138.70';
$_ENV['DB_USERNAME'] = 'u345239147_vas';
$_ENV['DB_PASSWORD'] = 'testing';
$_ENV['DB_DATABASE'] = 'u345239147_datab';

use VSQL\VSQL\Mold;

$v = new Mold( );
$v->smarty('tba');
$v->controller('tba');
$v->model('tba');
