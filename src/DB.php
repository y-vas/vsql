<?php

namespace VSQL\VSQL;

define( 'VSQL_NULL_FIELD' , 1 );

class DB {
    public $inspect;
    public $vquery='';
    public $cquery;
    public $vars;
    public $id;
    public $func;
    public $fetched = [];
    public $query;
    public $connect = false; # resource: DB connection
    public $error; # string: Error message
    public $errno; # integer: error no

// ------------------------------------------------ <  init > ----------------------------------------------------
    function __construct( $id = null ) {
        $this->id = $id;

        foreach (array('DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE') as $value) {
            if (!isset($_ENV[$value])) {
              $this->error("ENV value \$_ENV[" . $value . "] is not set!");
            }
        }

        $this->error = false;
        $this->errno = false;
        $this->inspect = isset($_ENV['VSQL_INSPECT']) ? $_ENV['VSQL_INSPECT'] : null;
        $this->vquery = '';
        $this->query = '';

        if (!function_exists('mysqli_connect')) {
            if (function_exists('mysqli_connect_error')) {
                $this->error = mysqli_connect_error();
            }
            if (function_exists('mysqli_connect_errno')) {
                $this->errorno = mysqli_connect_errno();
            }
            $this->error("Function mysqli_connect() does not exists. mysqli extension is not enabled?");
        }
    }

    public function connect() {
      $this->connect = mysqli_connect(
        $_ENV['DB_HOST'],
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        $_ENV['DB_DATABASE']
      );

      if (!$this->connect) {
        $this->error('Unable to connect to the database!');
      }

      $this->connect->query("
        SET character_set_results = 'utf8',
        character_set_client = 'utf8',
        character_set_connection = 'utf8',
        character_set_database = 'utf8',
        character_set_server = 'utf8'
      ");

      return $this->connect;
    }


    public function secure($var) {
        if (is_array($var)) {
            foreach ($var as $k=>$e) {
                $_newvar[$k] = $this->secure($e);
            }
            return $_newvar;
        }

        if (function_exists('mysqli_real_escape_string')) {
            return mysqli_real_escape_string($this->connect, $var);
        } elseif (function_exists('mysqli_escape_string')) {
            return mysql_escape_string($var);
        } else {
            return addslashes($var);
        }
    }

//------------------------------------------------ <  run > ------------------------------------------------------------
    public function run() {
        $mysqli = $this->connect;
        $mysqli->query( $this->vquery );
        return $mysqli;
    }


//------------------------------------------------ <  error > ------------------------------------------------------------
    protected function error( $msg , $code = 0 , $debug = false ) {
      if ($debug) { $_ENV['VSQL_INSPECT'] = true; }

      if (isset($_ENV['VSQL_INSPECT'])){ if ($_ENV['VSQL_INSPECT']){
        $content = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'info.html');

        $values = array(
          "ERROR_MESAGES" => $msg,
          "ORIGINALQUERY" => htmlentities($this->query),
          "TRANSFRMQUERY" => htmlentities($this->vquery),
        );

        foreach ($values as $key => $value) {
          $content = str_replace("<$key>", $value, $content);
        }

        die( $content );
      }}

      throw new \Exception("Error : " . $msg, $code );
    }

    protected function trace_func($func){
      $e = new \Exception();
      foreach ($e->getTrace() as $key => $value) {
          if ($value['function'] == $func ) {
              $bodytag = str_replace(DIRECTORY_SEPARATOR,"", $value['file']);
              $value['date'] = filemtime($value['file']);
              $value['json'] = $_ENV['VSQL_CACHE'].DIRECTORY_SEPARATOR."{$bodytag}.json";
              $value['real'] = file_exists($value['json']);
              return $value;
          }
      }

      return null;
    }

//------------------------------------------------ <  makemodel > ------------------------------------------------------
    public function model( $table ) {
        if (isset($_ENV['VSQL_INSPECT'])){if ($_ENV['VSQL_INSPECT']){

          $this->connect();
          $result = $this->connect->query( "SHOW COLUMNS FROM {$table} FROM " . $_ENV['DB_DATABASE'] );

          $tipe = array(
            'tinyint' => ['i',0] ,
            'smallint' => ['i',0] ,
            'int' => ['i',0] ,
            'float' => ['f',0] ,
            'double' => ['f',0] ,
            'timestamp' => ['i',0] ,
            'bigint' => ['i',0] ,
            'mediumint' => ['i',0] ,
            'date' => ['s',"''"] ,
            'time' => ['s',"''"] ,
            'datetime' => ['s',"''"] ,
            'year' => ['i',0] ,
            'bit' => ['i',0] ,
            'varchar' => ['s',"''"] ,
            'char' => ['s',"''"] ,
            'decimal' => ['f',0]
          );

          while($r = $result->fetch_assoc()) {
            $tp = $tipe[explode(  '(',$r['Type']  )[0]][0];
            $nn = $tipe[explode(  '(',$r['Type']  )[0]][1];

            $n = ($c++ % 3 == 0) ? "\n" : '';

            $select .= "$n`" . $r['Field'] .'`,' ;
            $where .= "$n{ AND `" . $r['Field'] ."` =  $tp:". $r['Field'] ." }" ;
            $insert .= "$n $tp:". $r['Field'] ." ? {$nn}; ," ;
            $update .= "$n{ ". $r['Field'] ." = $tp:". $r['Field'] ." ,}" ;
          }

          $select = rtrim($select,',');
          $insert = rtrim($insert,',');
          $update = rtrim($update,',}');
          $update .= '}';

          $q = "\$v->query(\"SELECT {$select}\nFROM {$table} WHERE TRUE {$where} \n\", \$arr);\n\n";
          $i = "\$v->query(\"INSERT INTO {$table} VALUES ({$insert}\n)\", \$arr);\n\n";
          $u = "\$v->query(\"UPDATE {$table} SET {$update} \n\", \$arr);\n";

          $this->error( $q.$i.$u );
        }}

        $this->error( "Set ( \$_ENV['VSQL_INSPECT'] = true; ) to enable model creation " );
    }

//------------------------------------------------ <  _cache > ---------------------------------------------------------
    private function chquery() {
        if (!isset($_ENV['VSQL_CACHE'])) { return; }
        if (empty( $this->id )) { return; }
        if (!file_exists($_ENV['VSQL_CACHE'])) {
            mkdir($_ENV['VSQL_CACHE'], 0755, true);
        }

        $data = $this->trace_func( 'query' );

        // if file doesn't exist create one
        if (!$data['real']){
            $f = fopen( $data['json'], "w");
            fwrite($f, json_encode(array(
              $this->id => [
                'update' => $data['date'],
                'vtvars' => $this->vars  ,
                'cquery' => $this->cquery,
              ]
            ), JSON_PRETTY_PRINT ));
            fclose( $f );
            return $this->cquery;
        }

        /* if file exists we get the content */
        $fcon = json_decode(utf8_decode(file_get_contents($data['json'])), true);

        /* if the file has not been updated we return the query */
        if ($fcon[ $this->id ]['update'] ==  $data['date']) {
          $this->vars = $fcon[$this->id]['vtvars'];
          return $fcon[$this->id]['cquery'];
        }

        $f = fopen( $data['json'], "w");
        fwrite($f, json_encode(array_merge(array(
          $this->id => [
            'update' => $data['date'],
            'vtvars' => $this->vars  ,
            'cquery' => $this->cquery,
            ]
          ), $fcon), JSON_PRETTY_PRINT ));
        fclose( $f );

        return $this->cquery;
    }





}
