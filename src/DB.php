<?php

namespace VSQL\VSQL;

class DB {

  //------------------------------------------------ <  _construct > -----------------------------------------------------
  function __construct($id = 0) {
      $this->id = $id;

      foreach (array('DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE') as $value) {
          if (!isset($_ENV[$value])) {
              $this->_error_msg("Enviroment value < \$_ENV[" . $value . "] > is not set!");
          }
      }

      $this->CONN = self::_conn();
      if ($this->CONN->connect_errno) {
          $this->_error_msg("Connection Fail: (" .
              $this->CONN->connect_errno
              . ") " . $this->CONN->connect_error
          );
      }
  }

//------------------------------------------------ <  _conn > ----------------------------------------------------------
    private function _conn() {
        return mysqli_connect(
            $_ENV['DB_HOST'],
            $_ENV['DB_USERNAME'],
            $_ENV['DB_PASSWORD'],
            $_ENV['DB_DATABASE']
        );
    }

//------------------------------------------------ <  _error_msg > -----------------------------------------------------
    public function _error_msg( $error_msg ) {

      if (isset($_ENV['VSLQ_DEBUG'])){ if ($_ENV['VSLQ_DEBUG']){
        $content = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'info.html');

        $values = array(
          "error_messages"    => $error_msg ,
          "original_query"    => htmlentities($this->query_original),
          "transformed_query" => htmlentities($this->query_string),
        );

        foreach ($values as $key => $value) {
          $content = str_replace("<$key>", $value, $content);
        }

        echo $content;
        die;
      }}

      throw new Exception("Error : " . $error_msg);
    }

//------------------------------------------------ <  makemodel > ------------------------------------------------------
    public function model( $table ) {
        $result = $this->CONN->query( "SHOW COLUMNS FROM {$table} FROM " . $_ENV['DB_DATABASE'] );

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
          $insert .= "$n $tp:". $r['Field'] .";{$nn}," ;
          $update .= "$n{ ". $r['Field'] ." = $tp:". $r['Field'] .";{$nn} }," ;
        }

        $select = rtrim($select,',');
        $insert = rtrim($insert,',');
        $update = rtrim($update,',');

        $q = "\$v->query(\"SELECT {$select}\nFROM {$table} WHERE TRUE {$where} \n\", \$arr);\n\n";
        $i = "\$v->query(\"INSERT INTO {$table} VALUES ({$insert}\n)\", \$arr);\n\n";
        $u = "\$v->query(\"UPDATE {$table} SET {$update} \n\", \$arr);\n";

        $this->_error_msg( $q.$i.$u );
    }

    public function compiler($str,$vrs){
      preg_match_all('!([^\s]*)((?::|{))(\w+)(;*)([^\s]*)!', $str, $match);

      var_dump($match);

    }
}
