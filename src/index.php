<?php

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'VSQL.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Template.php');

$_ENV['DEBUG'] = true;
$_ENV['DB_HOST']
$_ENV['DB_USERNAME']
$_ENV['DB_PASSWORD']
$_ENV['DB_DATABASE']


use VSQL\VSQL\VSQL;

$v = new VSQL();
$v->query("SELECT
  *
  from database
",array(),'debug');
