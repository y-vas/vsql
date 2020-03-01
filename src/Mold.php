<?php

namespace VSQL\VSQL;
require(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'DB.php');

class Mold extends DB {

  private $datatypes = array(
  /* datatype   |  parser   | default | html           |    */
  'tinyint'   =>[  'int'    ,  0      ,'number'        ,   0   ] ,
  'smallint'  =>[  'int'    ,  0      ,'number'        ,   0   ] ,
  'int'       =>[  'int'    ,  0      ,'number'        ,   0   ] ,
  'float'     =>[  'float'  ,  0.0    ,'number'        ,   0   ] ,
  'double'    =>[  'float'  ,  0.0    ,'number'        ,   0   ] ,
  'timestamp' =>[  'int'    ,  0      ,'number'        ,   0   ] ,
  'bigint'    =>[  'int'    ,  0      ,'number'        ,   0   ] ,
  'mediumint' =>[  'int'    ,  0      ,'number'        ,   0   ] ,
  'date'      =>[  'string' ,  "''"   ,'date'          ,   0   ] ,
  'time'      =>[  'string' ,  "''"   ,'time'          ,   0   ] ,
  'datetime'  =>[  'string' ,  "''"   ,'datetime-local',   0   ] ,
  'year'      =>[  'int'    ,  0      ,'number'        ,   0   ] ,
  'bit'       =>[  'int'    ,  0      ,'number'        ,   0   ] ,
  'varchar'   =>[  'string' ,  "''"   ,'text'          ,   0   ] ,
  'char'      =>[  'string' ,  "''"   ,'text'          ,   0   ] ,
  'decimal'   =>[  'float'  ,  0.0    ,'number'        ,   0   ]
  );

  public function __construct(){
    if (isset($_ENV['VSQL_INSPECT'])){if ($_ENV['VSQL_INSPECT']){
      $this->connect();
      return;
    }}

    $this->error( "Set ( \$_ENV['VSQL_INSPECT'] = true; ) to enable Mold Class" );
  }

  public function abstraction( $table ,$e = "\t"){
    $res = $this->connect->query( "SHOW COLUMNS FROM {$table} FROM " . $_ENV['DB_DATABASE'] );
    $max = mysqli_num_rows ( $res );

    while($r = $res->fetch_assoc()) {
      $exp = explode(  '(',$r['Type']  )[0];
      $ht = $this->datatypes[ $exp ][ 2 ];
      $tp = $this->datatypes[ $exp ][ 0 ][ 0 ];
      $nn = $this->datatypes[ $exp ][ 1 ][ 0 ];
      $pr = 'is_'.$this->datatypes[$exp][ 0 ];
      $f = $r['Field'];

      $n = ($c++ % 3 == 0) ? "\n$e" : '';
      $na = ($c % 8 == 0) ? "\n\t$e" : '';
      $t = ($c != $max) ? "," : '';

      if ($c == 1){ $data['ff'] = $f; }

      $select .= "$n`" . $f ."`$t" ;
      $where .=  "$n{ AND `" . $f ."` =  $tp:". $f ." }" ;
      $insert .= "$n $tp:". $f ." ? {$nn}; {$t}" ;
      $update .= ($c != $max) ? "$n{ ". $f ." = $tp:". $f ." ,}" : ''; ;

      $uf = $e.$f." = $tp:". $f;
      $update .= ($c == $max) ? " ".$uf."$e$n WHERE ".$uf : '';

      $parser .= $e ."\tif(!".$pr."(\$v) && \$k == '{$f}'){return null;}" . "\n";
      $array .= "$na'{$f}'{$t}";

      $name = ucfirst(strtolower($f));

      $l = "<label for='{$f}'>{$name}</label>";
      $l.= "\n$e\t<input type='{$ht}' class='form-control' id='{$f}' value='{\$obj->{$f}}' placeholder='{$name}'>";
      $s .= "$e<div class='form-group'>\n$e\t{$l}\n$e</div>\n";
    }

    $q = "$e\$v->query(\"SELECT {$select}\n".$e."FROM {$table} WHERE TRUE {$where} \n$e\", \$arr, false \n$e);\n\n";
    $i = "$e\$v->query(\"INSERT INTO {$table} VALUES ({$insert})\"\n$e, \$arr, false \n$e);\n\n";
    $u = "$e\$v->query(\"UPDATE {$table} SET {$update} \n$e\", \$arr, false \n$e);\n";
    $d = "$e\$v->query(\"DELETE FROM {$table} WHERE " . $data['ff'] . " = {\$id}\",[], false );\n";
    $a = " {$array} ";
    $p = " {$parser} \n";
    $r = "$e\$v->query(\"REPLACE {$table} SET {$update} \n$e\", \$arr, false \n$e);\n";

    return [ $data, $q,$i,$u,$d,$p,$a,$r,$s ];
  }

  public function model( $table /*, $type = 'static'*/){
    $abs = $this->abstraction($table,"\t\t");
    $classname = ucfirst(strtolower($table));

    $sel = "\n\tpublic function sel( \$arr, \$all = false){\n";
    $sel .= "\t\t\$v = new VSQL(); \n" . $abs[1];
    $sel .= "\n\t\treturn \$v->get(\$all);\n\t}";

    $add = "\n\tpublic function add( \$arr ){\n";
    $add .= "\t\t\$v = new VSQL(); \n" . $abs[2];
    $add .= "\n\t\treturn \$v->run()->insert_id;\n\t}";

    $upd = "\n\tpublic function upd( \$arr ){\n";
    $upd .= "\t\t\$v = new VSQL(); \n" . $abs[3];
    $upd .= "\n\t\treturn \$v->run();\n\t}";

    $del = "\n\tpublic function del( \$id ){\n";
    $del .= "\t\t\$v = new VSQL(); \n" . $abs[4];
    $del .= "\n\t\treturn \$v->run();\n\t}";

    $rep = "\n\tpublic function rep( \$arr ){\n";
    $rep .= "\t\t\$v = new VSQL(); \n" . $abs[7];
    $rep .= "\n\t\treturn \$v->run();\n\t}";

    $par = "\n\tpublic function parse( \$arr ){\n";
    $par .= "\n\t\tforeach([".$abs[6]."] as \$k ) {";
    $par .= "\n\t\t\t\$v = \$arr[\$k];";
    $par .= "\t\t\n" . $abs[5];
    $par .= "\t\t\t\$data[\$k] = \$v;\n\t\t}";
    $par .= "\n\t\treturn \$data;\n\t}";

    $inner = $par ."\n\n". $sel ."\n\n". $add ."\n\n". $del ."\n\n". $upd ."\n\n". $rep;
    $class = "<?php\nuse VSQL\VSQL\VSQL;\n\n";
    $class .= "class {$classname} {{$inner}\n}";

    return $class;
  }

  public function controller( $table /*, $type = 'smarty'*/ ){
    $abs = $this->abstraction($table,"\t\t");
    $id = $abs[0]['ff'];

    $classname = ucfirst(strtolower($table));

    $one = "\n\tpublic function showOne{$classname}(){\n";
    $one .= "\t\t\$details = {$classname}::parse(\$_GET); \n";
    $one .= "\t\tif( \$details == null ){return;} \n\n";
    $one .= "\t\t\$smarty->assign('obj'=>{$classname}::sel(\$details)); \n";
    $one .= "\n\t}";

    $all = "\n\tpublic function showAll{$classname}(){\n";
    $all .= "\t\t\$details = {$classname}::parse(\$_GET); \n";
    $all .= "\t\tif( \$details == null ){return;} \n\n";
    $all .= "\t\t\$smarty->assign('obj'=>{$classname}::sel(\$details,true)); \n";
    $all .= "\n\t}";

    $mod = "\n\tpublic function modifyOne{$classname}( ){\n";
    $mod .= "\t\t\$details = {$classname}::parse(\$_POST); \n";
    $mod .= "\t\tif( \$details == null ){return;}\n";
    $mod .= "\n\t\t{$classname}::rep(\$details);\n\t}";

    $upd = "\n\tpublic function updateOne{$classname}( ){\n";
    $upd .= "\t\t\$details = {$classname}::parse(\$_POST); \n";
    $upd .= "\t\tif( \$details == null ){return;}\n";
    $upd .= "\n\t\t{$classname}::upd(\$details);\n\t}";

    $add = "\n\tpublic function addOne{$classname}( ){\n";
    $add .= "\t\t\$details = {$classname}::parse(\$_POST); \n";
    $add .= "\t\tif( \$details == null ){return;}\n";
    $add .= "\n\t\t{$classname}::add(\$details);\n\t}";

    $del = "\n\tpublic function delOne{$classname}(){\n";
    $del .= "\t\t{$classname}::del(\$_GET['id']); \n";
    $del .= "\n\t\t\n\t}";

    $inner = $one . $all . $mod . $upd. $add. $del;
    $class = "<?php\nuse $classname;\n\n";
    $class .= "class Ctrl{$classname} {{$inner}\n}";

    return $class;
  }

  public function smarty( $table ){
    $abs = $this->abstraction($table,"\t");
    $id = $abs[0]['ff'];

    $classname = ucfirst(strtolower($table));

    $inner = $abs[8];
    $inner.= "\n\t<input type='submit' class='btn btn-primary' value='Save'>";
    $inner.= "\n\t<input type='submit' class='btn btn-danger' value='Delete'>";
    $class = "<form action='modifyOne{$classname}/{\$$id}' method='post'>\n{$inner}\n</form>";

    return $class;
  }
  public function makeMold( $table ,$dir = '') {
    if ($dir == ''){
      $dir = dirname($this->trace_func( 'makeMold' )['file']);
    }

    mkdir($dir , 0777);

    $sname = strtolower($table);
    $classname = ucfirst($sname);

    $f = fopen("{$dir}/{$sname}.tpl", "w");
    fwrite($f, $this->smarty($table));
    fclose($f);

    $f = fopen("{$dir}/{$classname}.php", "w");
    fwrite($f, $this->controller($table));
    fclose($f);

    $f = fopen("{$dir}/Model{$classname}.php", "w");
    fwrite($f, $this->model($table));
    fclose($f);


  }

}
