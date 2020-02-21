<?php

namespace VSQL\DB;

class DB {
    private $server; # string: DB Server
    private $user; # string: DB User
    private $pwd; # string: DB Password
    private $database; # string: DB database
    private $inspect;
    private $vquery;
    private $cquery;
    private $vars;
    private $id;
    private $func;
    private $query;
    private $connect = false; # resource: DB connection
    public $error; # string: Error message
    public $errno; # integer: error no

    function __construct() {

        foreach (array('DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE') as $value) {
            if (!isset($_ENV[$value])) {
              $this->error("ENV value \$_ENV[" . $value . "] is not set!");
            }
        }

        $this->error = false;
        $this->errno = false;
        $this->inspect = isset($_ENV['VSQL_INSPECT']) ? $_ENV['VSQL_INSPECT'] : null;
        $this->server = $_ENV['DB_HOST'];
        $this->user = $_ENV['DB_USERNAME'];
        $this->pwd = $_ENV['DB_PASSWORD'];
        $this->database = $_ENV['DB_DATABASE'];
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
        if (!$this->_connect()) {
          $this->error('Unable to connect to the database!');
        }
        if (!$this->selectdb()) {
          $this->error('Unable to select database!');
        }

        return $this->connect;
    }

    private function _connect() {
        if ($this->connect = @mysqli_connect(
            $this->server, $this->user, $this->pwd
                )) {
            return true;
        }
        return false;
    }

    private function selectdb() {
        if (@mysqli_select_db($this->connect, $this->database)) {
            return true;
        }
        return false;
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



    protected function error( $error_msg ) {

      if (isset($_ENV['VSQL_INSPECT'])){ if ($_ENV['VSQL_INSPECT']){
        $content = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'info.html');

        $values = array(
          "ERROR_MESAGES" => $error_msg,
          "ORIGINALQUERY" => htmlentities($this->vquery),
          "TRANSFRMQUERY" => htmlentities($this->query),
        );

        foreach ($values as $key => $value) {
          $content = str_replace("<$key>", $value, $content);
        }

        echo $content;
        die;
      }}

      throw new \Exception("Error : " . $error_msg);
    }

    private function trace_func($func){
      $e = new Exception();
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

        if (empty( $this->id )) { return;   }

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
            fclose($f);
            return $this->cquery;
        }

        /* if file exists we get the content */
        $fcon = json_decode(utf8_decode(file_get_contents($data['json'])), true);

        /* if the file has not been updated we return the query */
        if ($fcon[ $this->id ]['update'] ==  $data['date']) {
          $this->vars = $fcon[$this->id]['vtvars'];
          return $fcon[$this->id]['cquery']
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
