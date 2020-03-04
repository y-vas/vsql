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

        if (!isset($_ENV['VSQL_UTF8'])) {
          $_ENV['VSQL_UTF8'] = true;
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
        $_ENV[  'DB_HOST'  ],
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        $_ENV['DB_DATABASE']
      );

      if (!$this->connect) {
        $this->error('Unable to connect to the database!');
      }

      if ($_ENV['VSQL_UTF8']) {
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
