<?php
namespace VSQL\VSQL;

use VSQL\VSQL\Table;

$_ENV['VSQL_INSPECT'] = true;
$_ENV[  'DB_HOST'  ] = '185.224.138.70';
$_ENV['DB_USERNAME'] = 'u345239147_vas';
$_ENV['DB_PASSWORD'] = 'testing';
$_ENV['DB_DATABASE'] = 'u345239147_datab';


class TAB1 extends Table {

}

$tb = new TAB1();
