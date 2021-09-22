<?php

namespace VSQL\VSQL;

use VSQL\VSQL\Mold;

class MoldAPI {

  public function makeMold( $args ,$dir = '') {
    $mold = new Mold();
    $this->mold = $mold;

    $contr = [
      "general" => ''
    ];

    foreach ( $args->parts as $k => $urls ) {
      $table = $args->mold->tables->{$k} ?? null;

      $db = [];
      if ( $table != null) {
        $d = $mold->abstraction( $table );
        $db = $d;
        // dd( $data );
        $contr[$k] = "";
      }

      $rest = [
        "funcs" => [],
        "lfuncs" => [],
      ];

      foreach ( $urls as $i => $data ) {
        $data->table = $table ?? $k;
        $data->db = $db;
        $func_name  = substr(str_replace("/", "_", $data->url ), 1);

        preg_match_all('~{(\w+)}~',
          $func_name, $m , PREG_OFFSET_CAPTURE );

        $func_name  = preg_replace( '~(.*)_*{.*}~', '\1' , $func_name );
        $rfunc_name = str_replace( $k . "_" , "" , $func_name );

        /// rename the function
        if((strpos($func_name , $k ) !== false )
            && !in_array($rfunc_name,$rest['funcs']) ){
            $func_name = $rfunc_name;
        }

        $fargs = [];

        foreach ($m[1] as $mt ) {
          $fargs[] = $mt[0];
        }

        $argso = '$' . implode(',$', $fargs );

        var_dump( $data->url  );
        // var_dump( $data->func );
        echo "<hr>";

        $data->function = $func_name;
        $data->fargs = $fargs;
        $data->args = $argso;

        $rest["funcs"][] = $func_name;
        $rest["lfuncs"][] = $this->makefunction( $data );
      }

////////////////////////////////////////////////////////////////////////
      // dd( $rest );
      $this->makeController($rest);
    }

    $this->makeRoutes($args);
    dd( $rest );
////////////////////////////////////////////////////////////////////////
  }

  public function makefunction($data){
    $args = $data->args == '$' ? '' : $data->args;
    $line = $this->mold->uppline( $data->function );

    $table = $data->table;
    $classname = ucfirst(strtolower($table));


    $params_not_set = [];
    $table_params = $data->db[0]["db"] ?? [];
    $params = $data->params ?? [];
    $paramsk = array_keys((array)$params);
    $arrs = [];
    $metas = '';

    $maxlen = 0;
    foreach ($paramsk as $f) {
      $r = (array)$params;

      try {
        if (is_array($r[$f])) {
          $r = json_encode($r[$f]);
          $r = '';
        } else if (!is_string($r[$f])) {
          $r = strval($r[$f]);
        } else {
          $r = $r[$f];
        }

      } catch (\Exception $e) {
        // dd($r,$f);
        // throw $e;
      }

      $maxlen = (strlen($f) > $maxlen) ? strlen($f) : $maxlen;
    }

    // die;

    foreach ($table_params as $key => $p) {
      $f = $p['Field'];
      $arrs[] = $f;
      $maxlen = (strlen($f) > $maxlen) ? strlen($f) : $maxlen;
    }

    // echo "<hr>" . $maxlen;
    foreach ($paramsk as $f ) {
      if (!in_array( $f, $arrs  )) {
        $maxlen = (strlen($f) > $maxlen) ? strlen($f) : $maxlen;
        $ofs = str_repeat( ' ' , $maxlen - strlen( $f ) );
        $metas .= "\n\t\t\t\t\"{$f}\" $ofs=> \$arr[\"{$f}\"] $ofs ?? null ,";
      }
    }

    $six = $data->db[6] ?? '';

    $f = 'meta';
    $maxlen = (strlen($f) > $maxlen) ? strlen($f) : $maxlen;
    $ofs = str_repeat( ' ' , $maxlen - strlen( $f ) );
    $meta = "\"meta\" $ofs=> [{$metas}
      ],";

    $inner = '';
    if ( $data->func == 'put' ) {
      $inner = "
    {$classname}::add([
{$six}\t\t{$meta}
    ]);
      ";
    } elseif ($data->func == 'get') {
      $inner = "
    {$classname}::get([
{$six}\t\t{$meta}
    ]);
    ";
    }

    $data->compiled = "{$line}
  public function {$data->function}({$args}){
    {$inner}
  }";

    // dd($data);
    return $data;
  }

  public function makeController($rest){
    $table = '';
    $inner = '';

    foreach ($rest["lfuncs"] as $f) {
      $inner .= $f->compiled;
      $table = $f->table;
    }

    $class = "<?php\nnamespace App\Http\Controllers\\{$table};\n\n";
    $class .= "use App\Http\Controllers\\{$table}\\{$table};\n\n";
    $class .= "class API extends \App\Http\Controllers\Core\API {{$inner}\n}";

    $dir = $this->mold->_get_dir('makeMold').'/';
    $fl = fopen( $dir . $table.'.php' , "w" );
    fwrite($fl, $class );
    fclose($fl);

    // die;
  }

  public function makeRoutes($args){
    $class = "<?php\nuse Illuminate\Support\Facades\Route;\n\n";

    foreach ( $args->parts as $k => $urls ) {
      $table = $args->mold->tables->{$k} ?? $k;
      $class .= "use App\Http\Controllers\\{$table}\\{$table};\n";
    }

    $class .= "\n";

    foreach ( $args->parts as $k => $urls ) {
      $table = $args->mold->tables->{$k} ?? $k;
      foreach ( $urls as $i => $data ) {
        $class .= "Route::{$data->method}( '{$data->url}' , [ {$table}::class , '{$data->function}'   ]);\n";
      }
    }


    $dir = $this->mold->_get_dir('makeMold').'/';
    $fl = fopen( $dir . 'api.routes.php' , "w" );
    fwrite($fl, $class );
    fclose($fl);

    // die;
  }

}
