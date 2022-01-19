<?php

class DB {
    public $inspect;   // shows if you are in inspect mode
    public $vquery=''; // given query
    public $cquery; // compiled query
    public $vars; // vars used between each query
    public $id; // deprecated
    public $func; // deprecated
    public $fetched = [];
    public $query; // query used as

    public $connect = false; # resource: DB connection
    public $error;           # string: Error message
    public $errno;           # integer: error no

// ------------------------------------------------ <  init > ----------------------------------------------------
    function __construct( $id = null ) {
        $this->id = $id;

        foreach (array('DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE') as $value) {
            if (!isset($_ENV[$value])) {
              $this->error( "ENV value \$_ENV[" . $value . "] is not set!" );
            }
        }

        $this->error   = false;
        $this->errno   = false;
        $this->inspect = isset($_ENV['APP_DEBUG']) ? $_ENV['APP_DEBUG'] : null;
        $this->vquery  = '';
        $this->query   = '';

        if (!function_exists('mysqli_connect')) {

            if ( function_exists('mysqli_connect_error') ){
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
        $_ENV[   'DB_HOST'   ],
        $_ENV[ 'DB_USERNAME' ],
        $_ENV[ 'DB_PASSWORD' ],
        $_ENV[ 'DB_DATABASE' ]
      );

      if (!$this->connect) {
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

    public function secure( $var ) {
        if (is_array( $var )) {
            $_newvar = [];
            foreach ( $var as $k => $e ) {
              $_newvar[$k] = $this->secure( $e );
            }

            return $_newvar;
        }

        if (function_exists('mysqli_real_escape_string')) {
            return mysqli_real_escape_string( $this->connect, $var );
        } elseif (function_exists('mysqli_escape_string')) {
            return mysql_escape_string($var);
        } else {
            return addslashes( $var );
        }
    }

//------------------------------------------------ <  error > ------------------------------------------------------------
    protected function error( $msg , $code = 0 , $debug = false ) {
      if ( $_ENV['APP_DEBUG'] || $debug ) {
        // get the info wrapper for error
        $content = file_get_contents(dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'info.html');

        $values = array(
          "ERROR_MESAGES" => $msg,
          "ORIGINALQUERY" => htmlentities(  $this->query  ),
          "TRANSFRMQUERY" => htmlentities(  $this->vquery ),
        );

        foreach ($values as $key => $value ){
          $content = str_replace("<$key>", $value, $content);
        }

        die( $content );
      }

      throw new \Exception("Error : " . $msg );
    }

}
