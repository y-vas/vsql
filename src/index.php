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

i:namde
i:name
i:name

\{ a \}
{ AND `name` =  i:name  AND `pass` =  i:pass },
{ AND `xx` =  i:xx },

{ vas;
  { AND `xx` =  i:xx }
}

{ AND `xxx` =  i:xxx
  { AND `xxas` =  i:xx
      { AND `xx` = i:2xx }
    }
}

i:name i:pass i:xx s:xxx

{ name = i:name;0 },{ pass = i:pass;0 },{ xx = i:xx;0 },
{ xxx = i:xxx  }
FROM connections WHERE TRUE
{ AND `id` =  i:id },{ AND `ip` =  s:ip },
{ AND `host` =  s:host }


", array(
  'name' => 'vas',
  'xx' => '00',
  'xxx' => '111',
  'vas' => '111',
  'pass' => '2222'
),true);
