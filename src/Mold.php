<?php

namespace VSQL\VSQL;
require(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'DB.php');

class Mold extends DB {

  private $datatypes = array(
  /* datatype   |  parser   | default | html           |      */
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
  'decimal'   =>[  'float'  ,  0.0    ,'number'        ,   0   ] ,
  'text'      =>[  'string' ,  "''"   ,'text'          ,   0   ] ,
  'enum'      =>[  'string' ,  "''"   ,'text'          ,   0   ]
  );

  public function __construct(){
    if (isset($_ENV['VSQL_INSPECT'])){if ($_ENV['VSQL_INSPECT']){
      $this->connect();
      return;
    }}

    $this->error( "Set ( \$_ENV['VSQL_INSPECT'] = true; ) to enable Mold Class" );
  }

  public function abstraction( $table ,$e = "\t"){
    $id = 'none';
    $lenght = 0;
    $data = [];

    $res = $this->connect->query( "SHOW COLUMNS FROM {$table} FROM " . $_ENV['DB_DATABASE'] );
    while($r = $res->fetch_assoc()) {
      if ( $r['Extra'] == 'auto_increment' && $r['Type'] ){
        $id = $r['Field'];
        $data['id'] = $id;
      }

      $lenght = (strlen($r['Field']) > $lenght) ? strlen($r['Field']) : $lenght;
    }

    $res = $this->connect->query( "SHOW COLUMNS FROM {$table} FROM " . $_ENV['DB_DATABASE'] );
    $max = mysqli_num_rows( $res );

    $c      = 0 ;
    $s      = '';
    $select = '';
    $where  = '';
    $insert = '';
    $update = '';
    $parser = '';
    $array  = '';
    $search = '';
    $th     = '';
    $td     = '';


    while($r = $res->fetch_assoc()) {
      var_dump($r);

      $exp = explode(  '(',$r['Type']  )[0];
      $ht = $this->datatypes[ $exp ][ 2 ];
      $tp = $this->datatypes[ $exp ][ 0 ][ 0 ];
      $nn = $this->datatypes[ $exp ][ 1 ];
      $pr = 'is_'.$this->datatypes[$exp][ 0 ];
      $f = $r['Field'];

      $ofs = str_repeat( ' ' , $lenght - strlen( $f ) );

      $n = ($c++ % 3 == 0) ? "\n$e" : "\n$e";
      $na = ($c % 8 == 0) ? "\n\t$e" : '';
      $t = ($c != $max) ? "," : '';

      if ($c == 1){ $data['ff'] = $f; }

      $select .= "$n`" . $f ."`$ofs$t" ;
      $where .=  "$n{ AND `" . $f ."`$ofs =  $tp:". $f ."$ofs }" ;
      $insert .= "$n $tp:". $f ."{$ofs} ? {$nn}; {$t}" ;
      $update .= "$n{ ". $f ."{$ofs} = $tp:". $f ." {$ofs},}" ;

      $parser.= $e ."\t'{$f}' $ofs=> \$_GET['{$f}'] $ofs ?? null ,\n";
      $array.= $e ."\t'{$f}' $ofs=> \$_POST['{$f}'] $ofs ?? null ,\n";

      $name = ucfirst(strtolower($f));
      $search.= "\n\t<input class='form-control-sm' type='{$ht}' name='{$f}' {$ofs} placeholder='{$name}'>";
      $th    .= "\n\t\t\t\t<th scope='col'>   {$name}{$ofs}   </th>";
      $td    .= "\n\t\t\t\t<td scope='col'>   {{\$obj->{$f}}} {$ofs}  </th>";

      $l = "<label for='{$f}'>{$name}</label>";
      $l.= "\n$e\t<input type='{$ht}' class='form-control' name='{$f}' id='{$f}' value='{\$obj->{$f}}' placeholder='{$name}'>";
      $s .= "$e<div class='form-group'>\n$e\t{$l}\n$e</div>\n";
    }

    $update .= "$e$n $id = $id $e$n WHERE $e$n $id = i:$id" ;
    $where .=  "$n{ LIMIT i:limit { OFFSET i:offset } }" ;
    $where .=  "$n{ ORDER BY :order }" ;

    $q = "$e\$v->query(\"SELECT {$select}\n".$e."FROM {$table} WHERE TRUE {$where} \n$e\", \$arr, false \n$e);\n\n";
    $i = "$e\$v->query(\"INSERT INTO {$table} VALUES ({$insert})\"\n$e, \$arr, false \n$e);\n\n";
    $u = "$e\$v->query(\"UPDATE {$table} SET {$update} \n$e\", \$arr, false \n$e);\n";
    $d = "$e\$v->query(\"DELETE FROM {$table} WHERE " . $id . " = {\$id}\",\$arr, false );\n";
    $a = " {$array} ";
    $p = " {$parser} ";
    $r = "$e\$v->query(\"REPLACE {$table} SET {$update} \n$e\", \$arr, false \n$e);\n";

    return [ $data, $q,$i,$u,$d,$p,$a,$r,$s,$search,$th,$td ];
  }

  public function model( $table /*, $type = 'static'*/){
    $abs = $this->abstraction($table,"\t\t");
    $classname = ucfirst(strtolower($table));

    $sel = "\n\tpublic static function sel( \$arr, \$all = false){\n";
    $sel .= "\t\t\$v = new VSQL(); \n" . $abs[1];
    $sel .= "\n\t\treturn \$v->get(\$all);\n\t}";

    $add = "\n\tpublic static function add( \$arr ){\n";
    $add .= "\t\t\$v = new VSQL(); \n" . $abs[2];
    $add .= "\n\t\treturn \$v->run()->insert_id;\n\t}";

    $upd = "\n\tpublic static function upd( \$arr ){\n";
    $upd .= "\t\t\$v = new VSQL(); \n" . $abs[3];
    $upd .= "\n\t\treturn \$v->run();\n\t}";

    $del = "\n\tpublic static function del( \$id , \$arr = [] ){\n";
    $del .= "\t\t\$v = new VSQL(); \n" . $abs[4];
    $del .= "\n\t\treturn \$v->run();\n\t}";

    $rep = "\n\tpublic static function rep( \$arr ){\n";
    $rep .= "\t\t\$v = new VSQL(); \n" . $abs[7];
    $rep .= "\n\t\treturn \$v->run();\n\t}";

    $par = "\n\tpublic static function parse( \$arr ){\n";
    $par .= "\n\t\tforeach([".$abs[6]."] as \$k ) {";
    $par .= "\n\t\t\t\$v = \$arr[\$k];";
    $par .= "\t\t\t\$data[\$k] = \$v;\n\t\t}";
    $par .= "\n\t\treturn \$data;\n\t}";

    $inner = "\n\n". $sel ."\n\n". $add ."\n\n". $del ."\n\n". $upd ."\n\n". $rep;
    $class = "<?php\nuse VSQL\VSQL\VSQL;\n\n";
    $class .= "class Model{$classname} {{$inner}\n}";

    return $class;
  }

  public function controller( $table ){
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

  public function laravel_controller( $table ){
    $abs = $this->abstraction($table,"\t\t");
    $id = $abs[0]['id'];

    $classname = ucfirst(strtolower($table));

    $all  = "\n\n\tpublic function index(){";
    $all .= "\n\t\t\$search = array(\n$abs[5]\t\t\t'limit' => 10\n\t\t);\n";
    $all .= "\n\t\t\$obj = Model{$classname}::sel(\$search,true);";
    $all .= "\n\n\t\treturn view('{$classname}/index',
      'objs' => \$obj \n\t\t); ";
    $all .= "\n\t}\n";

    $mod = "\n\tpublic function compose(){\n";
    $mod .= "\n\t\t\$what = array(\n$abs[6]\t\t);\n";
    $mod .= "\n\t\tModel{$classname}::rep(\$what);\n\t}\n";

    $del = "\n\tpublic function del(\$id){\n";
    $del .= "\t\tModel{$classname}::del(\$id); \n";
    $del .= "\n\t}\n";

    $shw  = "\n\n\tpublic function show(\$id){";
    $shw .= "\n\t\t\$obj = Model{$classname}::sel(array('$id'=>\$id));";
    $shw .= "\n\n\t\tif (!isset(\$obj->$id)){
      return;
    }";
    $shw .= "\n\n\t\treturn view('{$classname}/index',
      'obj' => \$obj \n\t\t); ";
    $shw .= "\n\t}\n";

    $inner = $all . $shw. $mod . $del;
    $class = "<?php\nnamespace App\Http\Controllers\\{$classname};\n\n";
    $class .= "use $classname;\n\n";
    $class .= "class Ctrl{$classname} extends Controller {{$inner}\n}";

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

  public function blade_list( $table ){
    $abs = $this->abstraction($table,"\t");
    $id = $abs[0]['ff'];

    $search = $abs[ 9];
    $th     = $abs[10];
    $td     = $abs[11];

    $buttons = "\n<button class='btn btn-sm btn-warning' type='button' name='button'>Add</button>";
    $buttons.= "\n";

    $thead = "<thead class='table-active'>\n\t\t\t<tr>{$th}\n\t\t</tr>\n\t</thead>";
    $tbody = "\t<tbody>\n\t\t\t@foreach (\$objs as \$obj)\n\t\t\t<tr>{$td}\n\t\t</tr>\n\t\t@endforeach\n\t</tbody>";
    $table = "<table class='table'>\n\t{$thead}\n\t{$tbody}\n</table>";

    $blade = "@extends('base')\n\n";
    $blade.= "@section('search'){$search}\n\t<button class='btn btn-sm btn-info' type='submit' >Search</button>\n@endsection\n\n";
    $blade.= "@section('main-buttons'){$buttons}@endsection\n\n";
    $blade.= "@section('container')\n{$table}\n@endsection\n";

    return $blade;
  }


  public function blade_show( $table ){
    $abs = $this->abstraction($table,"\t");
    $id = $abs[0]['id'];

    $classname = ucfirst(strtolower($table));

    $buttons = "\n<button class='btn btn-sm btn-warning' type='button' name='button'>Delete</button>";
    $buttons.= "\n";

    $blade ="@extends('base')\n\n";
    $blade.= "@section('main-buttons'){$buttons}@endsection\n\n";

    $inner = $abs[8];
    $inner.= "\n\t<input type='submit' class='btn btn-primary' value='Save'>";
    $inner.= "\n\t<a href='.../del/{{\$obj->id}}' class='btn btn-primary'> Delete </a>";
    $class = "<form action='.../compose/{{ \$obj->$id ?? 0 }}' method='post'>\n{$inner}\n</form>";
    $blade.= "@section('container')\n{$class}\n@endsection\n";

    return $blade;
  }


  public function makeMold( $table ,$dir = '') {
    $sname = strtolower($table);
    $classname = ucfirst($sname);
    $gitignore = "__mold__";

    echo "<pre>";

    if ($dir == ''){
      $diro = dirname($this->trace_func( 'makeMold' )['file']);
      $f = fopen("{$diro}/.gitignore", "w");
      fwrite($f, '__mold__*/');
      fclose($f);
      $dir = $diro . DIRECTORY_SEPARATOR . $gitignore . $classname;
    }

    try {
      mkdir($dir , 0777);
    } catch (\Exception $e) { }

    $files = array(
      ['name'=> "/{$sname}.tpl"         , 'func'=>'smarty'],
      ['name'=> "/index.blade.php"      , 'func'=>'blade_list'],
      ['name'=> "/show.blade.php"       , 'func'=>'blade_show'],
      ['name'=> "/{$classname}.php"     , 'func'=>'controller'],
      ['name'=> "/Controller.php"       , 'func'=>'laravel_controller'],
      ['name'=> "/Model{$classname}.php", 'func'=>'model'],
    );

    foreach ($files as $key => $f) {
      $fl = fopen( $dir . $f['name'] , "w" );
      eval('fwrite($fl, $this->'.$f['func'].'($table));');
      fclose($fl);
    }

    die;
  }

}
