<?php

namespace VSQL\VSQL;

class Mold {
  public $inspect;   // shows if you are in inspect mode
  public $vquery=''; // given query
  public $cquery; // compiled query
  public $vars; // vars used between each query
  public $fetched = [];
  public $query; // query used as
  public $connect = false; # resource: DB connection
  public $error; # string: Error message
  public $errno; # integer: error no

  private $datatypes = array(
    /* datatype          |  parser   | default | html           |      */
    'tinyint'          =>[  'int'    ,  0      ,'number'        ,   0   ] ,
    'smallint'         =>[  'int'    ,  0      ,'number'        ,   0   ] ,
    'int'              =>[  'int'    ,  0      ,'number'        ,   0   ] ,
    'bigint unsigned'  =>[  'int'    ,  0      ,'number'        ,   0   ] ,
    'float'            =>[  'float'  ,  0.0    ,'number'        ,   0   ] ,
    'double'           =>[  'float'  ,  0.0    ,'number'        ,   0   ] ,
    'timestamp'        =>[  'int'    ,  0      ,'number'        ,   0   ] ,
    'bigint'           =>[  'int'    ,  0      ,'number'        ,   0   ] ,
    'mediumint'        =>[  'int'    ,  0      ,'number'        ,   0   ] ,
    'date'             =>[  'string' ,  "''"   ,'date'          ,   0   ] ,
    'time'             =>[  'string' ,  "''"   ,'time'          ,   0   ] ,
    'datetime'         =>[  'string' ,  "''"   ,'datetime-local',   0   ] ,
    'year'             =>[  'int'    ,  0      ,'number'        ,   0   ] ,
    'bit'              =>[  'int'    ,  0      ,'number'        ,   0   ] ,
    'varchar'          =>[  'string' ,  "''"   ,'text'          ,   0   ] ,
    'char'             =>[  'string' ,  "''"   ,'text'          ,   0   ] ,
    'decimal'          =>[  'float'  ,  0.0    ,'number'        ,   0   ] ,
    'text'             =>[  'string' ,  "''"   ,'text'          ,   0   ] ,
    'longtext'         =>[  'string' ,  "''"   ,'text'          ,   0   ] ,
    'enum'             =>[  'string' ,  "''"   ,'text'          ,   0   ]
  );

  public function __construct(){
    if (isset($_ENV['APP_DEBUG'])){if ($_ENV['APP_DEBUG']){
      $this->connect();
      return;
    }}
    $this->error( "Set ( \$_ENV['APP_DEBUG'] = true; ) to enable Mold Class" );
  }

  public function connect() {
    $this->connect = mysqli_connect(
      $_ENV[  'DB_HOST'  ],
      $_ENV['DB_USERNAME'],
      $_ENV['DB_PASSWORD'],
      $_ENV['DB_DATABASE']
    );

    if ( !$this->connect ) {
      $this->error('Unable to connect to the database!');
    }

    if (isset($_ENV['VSQL_UTF8'])) {
      $this->connect->query("
        SET
        character_set_results    = 'utf8',
        character_set_client     = 'utf8',
        character_set_connection = 'utf8',
        character_set_database   = 'utf8',
        character_set_server     = 'utf8'
      ");
    }

    return $this->connect;
  }

//~~~~ uppline ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  public function uppline( $name , $extra = '' ){
    $len = strlen( $name );
    $line = "\n// ~~~~ $name " . str_repeat( '~' , 71 - $len );
    if (!empty( $extra )) {
      $len = strlen( $extra );
      $line .= "\n/* .... $extra ". str_repeat( '.' , 69 - $len ) . '*/';
    }
    return $line;
  }

//~~~~ abstraction ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  public function abstraction( $table ,$e = "\t"){
    $id     = 'none';
    $lenght = 0;

    $data = [
      'order' => 'ORDER_COLUMN',
      'clone' => '',
      'db' => []
    ];

    $res = $this->connect->query( "SHOW COLUMNS FROM {$table} FROM " . $_ENV['DB_DATABASE'] );
    while($r = $res->fetch_assoc()) {
      if ( $r['Extra'] == 'auto_increment' && $r['Type'] ){
        $id = $r['Field'];
        $data['id'] = $id;
      }
      $data['db'][] = $r;

      if ( strpos(strtolower($r['Field']),'ord') !== false && $r['Type'] == 'int'){
        $data['order'] = $r['Field'];
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
    $blade  = '';

    while($r = $res->fetch_assoc()) {
      var_dump( $r );

      $exp = explode(  '(',$r['Type']  )[0];

      // echo $exp;
      // die;

      $ht = $this->datatypes[ $exp ][ 2 ];
      $tp = $this->datatypes[ $exp ][ 0 ][ 0 ];
      $nn = $this->datatypes[ $exp ][ 1 ];
      $pr = 'is_'.$this->datatypes[$exp][ 0 ];
      $f = $r['Field'];

      $ofs = str_repeat( ' ' , $lenght - strlen( $f ) );

      $n = ( $c++ % 3 == 0 ) ? "\n$e" : "\n$e";
      $na =( $c   % 8 == 0 ) ? "\n\t$e" : '';
      $t = ( $c   != $max  ) ? "," : '';

      if ( $c == 1 ){
        $data['ff'] = $f;
      }

      $select .= "$n`" . $f ."`$ofs$t" ;
      $where  .= "$n{ AND `" . $f ."`$ofs =  $tp:". $f ."$ofs }" ;
      $insert .= "$n $tp:". $f ."{$ofs} ? {$nn}; {$t}";
      $update .= "$n{ ". $f ."{$ofs} = $tp:". $f ." {$ofs},}" ;

      $parser.= $e ."\t'{$f}' $ofs=> \$_GET['{$f}'] $ofs ?? null ,\n";
      $array.= $e ."\t'{$f}' $ofs=> \$arr['{$f}'] $ofs ?? null ,\n";
      $data['clone'] .= $e ."\t'{$f}' $ofs=> \$obj->{$f} $ofs ,\n";

      $name    = ucfirst(strtolower($f));
      $search .= "\n\t<input class='form-control-sm' type='{$ht}' name='{$f}' {$ofs} placeholder='{$name}'>";
      $th     .= "\n\t\t\t\t<th scope='col'> {$name}{$ofs} </th>";

      if ($f == 'id') {
        $td .= "\n\t\t\t\t<td scope='col'>";
        $td .= "\n\t\t\t\t\t<a href='/{$name}/show/{{\$obj->id}}'> {{\$obj->name}} {$ofs}  </a>";
        $td .= "\n\t\t\t\t</td>";
      }else {
        $td .= "\n\t\t\t\t<td scope='col'>   {{\$obj->{$f}}} {$ofs}  </td>";
      }

      $l = "<label for='{$f}'>{$name}</label>";
      $b = "<label for='{$f}'>{$name}</label>";
      $l.= "\n$e\t<input type='{$ht}' class='form-control' name='{$f}' id='{$f}' value='{\$obj->{$f}}' placeholder='{$name}'>";
      $b.= "\n$e\t<input type='{$ht}' class='form-control' \n\t\tname='{$f}' id='{$f}' value='{{\$obj->{$f}}}' placeholder='{$name}'>";
      $s .= "$e<div class='form-group'>\n$e\t{$l}\n$e</div>\n";
      $blade .= "$e<div class='form-group'>\n$e\t{$b}\n$e</div>\n";
    }

    $update .= "$e$n $id = $id
    { STATUS = !STATUS  toggle; }
    WHERE $e$n $id = i:$id" ;
    $data['count'] = "$e\$v->query(\"SELECT COUNT(*) as count FROM {$table} WHERE TRUE {$where} \n$e\", \$arr, false \n$e);\n\n";
    $where .=  "$n{ AND `$id` != i:n$id               }" ;
    $where .=  "$n{ ORDER BY      :order              }" ;
    $where .=  "$n{ LIMIT i:limit { OFFSET i:offset } }" ;

    $q = "FROM {$table} WHERE TRUE {$where} \n$e\", \$arr, false \n$e);\n\n";
    $i = "$e\$v->query(\"INSERT INTO {$table} VALUES ({$insert})\"\n$e, \$arr, false \n$e);\n\n";
    $u = "$e\$v->query(\"UPDATE {$table} SET {$update} \n$e\", \$arr, false \n$e);\n";
    $a = " {$array} ";
    $p = " {$parser} ";
    $r = "$e\$v->query(\"REPLACE {$table} SET {$update} \n$e\", \$arr, false \n$e);\n";

    $data['blade'] = $blade;

    return [ $data, $q, $i, $u, null, $p, $a, $r, $s, $search, $th, $td ];
  }

  public function model( $table /*, $type = 'static'*/ ){
    $abs = $this->abstraction($table,"\t\t");
    $classname = ucfirst(strtolower($table));
    $id = $abs[0]['id'];
    $ord = $abs[0]['order'];

    $sel = self::uppline('get');
    $sel .= "\n\tpublic static function get( \$arr, \$all = false ){\n";
    $sel .= "\t\t\$v = new VSQL();

    \$v->query(\"SELECT {
    { count(*) as `num` count; }
    { `$id`  _id; }
      default: *
    } " . $abs[1];
    $sel .= "\t\treturn \$v->get(\$all);\n\t}";

    $count = self::uppline('count');
    $count .= "\n\tpublic static function count( \$arr ){\n";
    $count .= "\t\t\$obj = self::get(
      array_merge(\$arr , ['count' => true ])
    );\n";
    $count .= "\n\t\treturn \$obj->num;\n\t}";

    $clone = self::uppline('clone');
    $clone .= "\n\tpublic static function clone( \$arr ){\n";
    $clone .= "\t\t\$obj = self::get([ '$id' => \$arr['$id'] ]);\n";
    $clone .= "\n\t\t\$id = self::add([\n".$abs[0]['clone']."\t\t]); \n";
    $clone .= "\n\t\t\$obj->$id = \$id; \n";
    $clone .= "\n\t\treturn \$obj;\n\t}";

    $add = self::uppline('add');
    $add .= "\n\tpublic static function add( \$arr ){\n";
    $add .= "\t\t\$v = new VSQL(); \n" . $abs[2];
    $add .= "\n\t\treturn \$v->run()->insert_id;\n\t}";

    $upd = self::uppline('upd');
    $upd .= "\n\tpublic static function upd( \$arr ){\n";
    $upd .= "\t\t\$v = new VSQL(); \n" . $abs[3];
    $upd .= "\n\t\treturn \$v->run();\n\t}";

    $del = self::uppline('del');
    $del .= "\n\tpublic static function del( \$arr ){\n";
    $del .= "\t\t\$v = new VSQL();

    \$v->query(\"DELETE FROM {$table} WHERE
      { $id = +i:$id }
      \", \$arr, false
    );\n";
    $del .= "\n\t\treturn \$v->run();\n\t}";

    $rep = self::uppline('rep');
    $rep .= "\n\tpublic static function rep( \$arr ){";
    $rep.= "\n\t\t\$id = !empty(\$arr['$id']) ? \$arr['$id'] : null;";
    $rep.= "\n\t\tif (\$id != null){
      self::upd(\$arr);
    }else{
      \$id = self::add( \$arr );
    }

    return \$id;
  }
    ";

    $sort = self::uppline('sort');
    $sort.= "\n\tpublic static function sort( \$id, \$put ){";
    $sort.= "\n\t\t\$elem = self::get(['$id' => \$id ]);";
    $sort.= "\n\t\t\$pos = \$elem->$ord;
    \$zon = \$elem->zone;

    if (\$id == null) return;
    \$sorts = self::get([
      '_id'   => true,
			'zone'  => \$zon,
			'order' => '$ord',
			'n$id' => \$id
		], true );

    foreach (\$sorts as \$k => \$v) {
      if (\$k >= \$put) \$k++;
			self::upd(['$id'=>\$v->$id,'$ord'=>\$k]);
		}

    self::upd(['$id'=> \$id,'$ord'=> \$put ]);
  }";

    $inner = implode("\n\n",[ $sel ,$count, $add, $del, $upd, $rep, $sort,$clone]);
    $class = "<?php\nnamespace App\Http\Controllers\\{$classname};\n";
    $class .= "\nuse VSQL\VSQL\VSQL;\n\n";
    $class .= "class {$table} {{$inner}\n}";

    return $class;
  }

  public function controller( $table ){
    $abs = $this->abstraction($table,"\t\t");
    $id = $abs[0]['id'];

    $name = strtolower($table);
    $classname = ucfirst($name);

    $all = self::uppline('index');
    $all .= "\n\tpublic function index(){";
    $all .= "\n\t\t\$limit = 10;\n";
    $all .= "\n\t\t\$page = ((\$_GET['page'] ?? 1) - 1) * \$limit;\n";
    $all .= "\n\t\t\$search = [\n$abs[5]\t\t\t'limit'  => \$limit,\n\t\t\t'offset' => \$page\n\t\t];\n";
    $all .= "\n\n\t\treturn view('{$table}/index',[
      'objs'=> {$table}::get(\$search, true ),
      'max' => ceil( {$table}::count( \$search ) / \$limit )
    ]); ";
    $all .= "\n\t}\n";

    $shw = self::uppline('show');
    $shw .= "\n\tpublic function show(){";
    $shw .= "\n\t\t\$obj = {$table}::get(['$id'=>request()->route('id')]);";
    $shw .= "\n\n\t\tif ( !isset( \$obj->$id ) ){
      Utils::redirect( '/{$name}' , __('office.item_not_found',['name'=>'$table']) , 'danger');
    }";
    $shw .= "\n\n\t\treturn view('{$table}/show',[
      'obj' => \$obj \n\t\t]); ";
    $shw .= "\n\t}\n";

    $mod = self::uppline('static ~ compose');
    $mod .= "\n\tprivate static function compose( \$arr ){\n";
    $mod .= "\n\t\t\$what = [\n$abs[6]\t\t];\n";
    $mod .= "\n\t\treturn {$table}::rep(\$what);\n\t}\n";

    $edit = self::uppline('edit');
    $edit .= "\n\tpublic function edit( ){
    \$id = self::compose(\$_POST);
    Utils::redirect( \"/{$name}/show/{\$id}\" , __('office.success') , 'success');
  }\n";

    $ajaxedit = self::uppline('ajax ~ edit');
    $ajaxedit .= "\n\tpublic function ajaxedit( ){
    \$id = self::compose(\$_POST);

    die(json_encode([
			'success' => true,
      'id' => \$id
		]));
  }\n";

    $toggle = self::uppline('ajax ~ toggle');
    $toggle .= "\n\tpublic function toggle( ){
    {$table}::upd([
      '$id' => request()->route('id'),
      'toggle' => true,
    ]);

    die(json_encode([
      'success' => true,
    ]));
  }\n";

    $sort = self::uppline('sort');
    $sort .= "\n\tpublic function sort(){";
    $sort .= "\n\t\t\$id = request()->route('id');";
    $sort .= "
    if (!isset(\$_GET['pos'])){
      die(json_encode([  'success'=> false ]));
    }

    {$table}::sort( \$id , \$_GET['pos'] );

    die(json_encode([
			'success' => true,
		]));\n\t}\n";

    $del = self::uppline('del');
    $del .= "\n\tpublic function del(){\n";
    $del .= "\t\t{$table}::del([
      '$id' => request()->route('id')
    ]);

    Utils::redirect( '/{$name}'  , 'Item-Deleted' , 'success');
  }\n";

    $ajaxdel = self::uppline('ajax ~ del');
    $ajaxdel .= "\n\tpublic function adel(){\n";
    $ajaxdel .= "\t\t{$table}::del([
      '$id' => request()->route('id')
    ]);

    die(json_encode([
			'success' => true,
		]));\n\t}\n";


// -----------------------------------
    $dwld = self::uppline('download');
    $dwld .= "\n\tpublic function dwld(){
      header( 'Content-Disposition: attachment; filename=\"{$table}.csv\";' );
      \$obj = {$table}::get([],'output-csv');
      die;
    }\n";

    $inner = $all . $shw . $dwld . $mod . $edit .$ajaxedit . $toggle . $sort .$del .$ajaxdel;
    $class = "<?php\nnamespace App\Http\Controllers\\{$table};\n\n";
    $class .= "use App\Http\Controllers\\{$table}\\{$table};\n\n";
    $class .= "class Controller extends \App\Http\Controllers\Core {{$inner}\n}";

    return $class;
  }

  public function blade_list( $table ){
    $abs = $this->abstraction($table,"\t");
    $id = $abs[0]['id'];

    $search = $abs[ 9];
    $th     = $abs[10];
    $td     = $abs[11];

    $buttons = "\n<a href='show/0' class='btn btn-sm btn-warning'> {{__('office.add')}} </a>";
    $buttons.= "\n";

    $thead = "<thead class='table-active'>\n\t\t\t<tr>{$th}\n\t\t</tr>\n\t</thead>";
    $tbody = "\t<tbody>\n\t\t\t@foreach (\$objs as \$obj)\n\t\t\t<tr>{$td}\n\t\t</tr>\n\t\t@endforeach\n\t</tbody>";
    $table = "<table class='table'>\n\t{$thead}\n\t{$tbody}\n</table>";

    $pagination = "\n\n@component('components.page',[ 'max' => \$max ])@endcomponent";

    $blade = "@extends('office')\n\n";
    $blade.= "@section('search'){$search}\n\t<button class='btn btn-sm btn-info' type='submit' >Search</button>\n@endsection\n\n";
    $blade.= "@section('content-header'){$buttons}@endsection\n\n";
    $blade.= "@section('content')\n{$table}{$pagination}\n\n@endsection\n";

    return $blade;
  }

  public function blade_show( $table ){
    $abs = $this->abstraction($table,"\t");
    $id = $abs[0]['id'];

    $sname     = strtolower( $table );
    $classname = ucfirst($sname);

    $buttons = "\n<button class='btn btn-sm btn-warning' type='button' name='button'>Delete</button>";
    $buttons.= "\n";

    $blade ="@extends('base')\n\n";
    $blade.= "@section('main-buttons'){$buttons}@endsection\n\n";

    $inner = $abs[0]['blade'];
    $inner.= "\n\t<input type='submit' class='btn btn-primary' value='Save'>";
    $inner.= "\n\t<a href='/{$sname}/del/{{\$obj->id}}' class='btn btn-primary'> Delete </a>";
    $class = "<form action='/{$sname}/edit/{{ \$obj->id ?? 0 }}' method='post' enctype=\"multipart/form-data\">
    @csrf
    \n{$inner}\n</form>";
    $blade.= "@section('container')\n{$class}\n@endsection\n";

    return $blade;
  }


  public function routes_office( $table ){
    $abs = $this->abstraction($table,"\t\t");

    $name = strtolower($table);
    $classname = ucfirst($name);

    $routes = self::uppline( $classname );
    $routes .= "\nRoute::prefix('{$name}')->group(function() {
  Route::get(  '/'           , [ Office::class , 'index'   ]);
  Route::post( '/edit/{id}'  , [ Office::class , 'edit'    ]);
  Route::post( '/status/{id}', [ Office::class , 'status'  ]);
  Route::get(  '/dwld/{id}'  , [ Office::class , 'dwld'    ]);
  Route::get(  '/show/{id}'  , [ Office::class , 'show'    ]);
  Route::get(  '/clone/{id}' , [ Office::class , 'clone'   ]);
  Route::get(  '/del/{id}'   , [ Office::class , 'del'     ]);
  Route::get(  '/import'     , [ Office::class , 'import'  ]);
});\n";

    $inner =  $routes ;
    $class = "<?php\nuse Illuminate\Support\Facades\Route;
use \App\Http\Controllers\{$table}\Client;\n\n";
    $class .= "{$inner}";

    return $class;
  }

  public function routes_client( $table ){
    $abs = $this->abstraction($table,"\t\t");

    $name = strtolower($table);
    $classname = ucfirst($name);

    $routes = self::uppline( $classname );
    $routes .= "\nRoute::prefix('{$name}')->group(function() {
  Route::get(  '/'           , [ Client::class , 'index'   ]);
});\n";

    $inner =  $routes ;
    $class = "<?php\nuse Illuminate\Support\Facades\Route;
use \App\Http\Controllers\{$table}\Client;\n\n";
    $class .= "{$inner}";

    return $class;
  }

  public function _get_dir( $func , $gi = true){
    $gitignore = "__mold__";

    $diro = dirname($this->trace_func( $func )['file']);
    if ($gi) {
      $f    = fopen("{$diro}/.gitignore", "w");
      fwrite( $f , '__mold__*/' );
      fclose( $f                );
    }

    $dir = $diro . DIRECTORY_SEPARATOR . $gitignore;
    try {
      mkdir($dir , 0777);
    } catch (\Exception $e){

    }

    return $dir;
  }

  public function makeMold( $table ,$dir = '') {
    $sname     = strtolower( $table );
    $classname = ucfirst($sname);
    $gitignore = "__mold__";

    echo "<pre>";

    if ($dir == ''){
      $dir = $this->_get_dir('makeMold');
    }


    $files = array(
      ['name'=> "/list.blade.php"     , 'func' => 'blade_list'    ],
      ['name'=> "/compose.blade.php"  , 'func' => 'blade_show'    ],

      ['name'=> "/client.routes.php"  , 'func' => 'routes_client' ],
      ['name'=> "/office.routes.php"  , 'func' => 'routes_office' ],

      ['name'=> "/Office.php"         , 'func' => 'controller'    ],
      ['name'=> "/Client.php"         , 'func' => 'controller'    ],
      ['name'=> "/API.php"            , 'func' => 'controller'    ],

      ['name'=> "/{$table}.php"       , 'func' => 'model'         ],
    );

    foreach ($files as $key => $f) {
      $fl = fopen( $dir . $f['name'] , "w" );
      eval( 'fwrite($fl, $this->'.$f['func'].'($table));' );
      fclose($fl);
    }

    die;
  }

  protected function trace_func($func){
    $e = new \Exception();
    foreach ( $e->getTrace() as $key => $value ){
        if ($value['function'] == $func ) {
            $bodytag = str_replace(DIRECTORY_SEPARATOR,"", $value['file']);
            $value['date'] = filemtime($value['file']);
            $value['json'] = DIRECTORY_SEPARATOR."{$bodytag}.json";
            $value['real'] = file_exists($value['json']);
            return $value;
        }
    }

    return null;
  }

  //------------------------------------------------ <  error > ------------------------------------------------------------
  protected function error( $msg , $code = 0 , $debug = false ) {
    throw new \Exception("Error : " . $msg, $code );
  }

}
