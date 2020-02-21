<?php

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'VSQL.php');

$_ENV['VSLQ_DEBUG'] = true;

$_ENV['DB_HOST'] = '185.224.138.70';
$_ENV['DB_USERNAME'] = 'u345239147_vas';
$_ENV['DB_PASSWORD'] = 'testing';
$_ENV['DB_DATABASE'] = 'u345239147_datab';

use VSQL\VSQL\VSQL;

$v = new VSQL();

$v->query("AND `xx` =  i:xx 
", array(
  'name' => 'vas',
  'xx' => '00',
  'xxx' => '111',
  'pass' => '2222'
),'debug');


// { a }
// { AND `name` =  i:name  AND `pass` =  i:pass },
// { AND `xx` =  i:xx },
// {vas;{ AND `xx` =  i:xx }}
// { AND `xxx` =  s:xxx
// { AND `xx` =  i:xx
//   { AND `xx` =  i:1xx }}
// }

// i:name;0, i:pass;0, i:xx;0,
// s:xxx;''
//
// { name = i:name;0 },{ pass = i:pass;0 },{ xx = i:xx;0 },
// { xxx = s:xxx;'' }
// FROM connections WHERE TRUE
// { AND `id` =  i:id },{ AND `ip` =  s:ip },{ AND `host` =  s:host }
