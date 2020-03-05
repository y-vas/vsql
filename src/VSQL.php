<?php

namespace VSQL\VSQL;
require(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'DB.php');


//                                           ██╗     ██╗ ███████╗  ██████╗  ██╗
//                                           ██║    ██║ ██╔════╝ ██╔═══██╗ ██║
//                                           ██║   ██║ ███████╗ ██║   ██║ ██║
//                                          ╚██╗  ██║ ╚════██║ ██║▄▄ ██║ ██║
//                                           ╚████╔╝  ███████║╚ ██████║ ███████╗
//                                             ╚═══╝   ╚══════╝ ╚══▀▀═╝ ╚══════╝

class VSQL extends DB {
  public function __construct( $id = null ) {
    $this->connect();
  }

//------------------------------------------------ <  query > ----------------------------------------------------------
  public function query($str, $vrs, $strict = false , $debug = false) {
    $this->query  = $str;
    $this->vars   = $vrs;
    $this->strict = $strict;

    $str = $this->compiler( $str, $vrs );
    $str = $this->modifier( $str, $vrs );

    $this->vquery = $str;
    if ( $debug ){ $this->error('Inspect', 0 , true ); }

    return $this->vquery;
  }

  public function run($list = false) {
      $qtipe = strtolower(explode(' ',trim($this->vquery))[0]);

      if ($qtipe == 'select') {
        return $this->get( $list );
      }

      $mysqli = $this->connect;
      $mysqli->query( $this->vquery );
      return $mysqli;
  }

//------------------------------------------------ <  compiler > ----------------------------------------------------------
  protected function compiler($str,$vrs,$cache = false){
    preg_match_all('~(?:([^\s,=]*)\s*(:)\s*(\w+)\s*(?(?=\?)\?([^;]*);|(!*))|([^\s{\)]*)(;)|(\\\\{0,1}{)|(\\\\{0,1}}))~', $str, $m , PREG_OFFSET_CAPTURE );

    $ofst = 0;
    $co = '';
    foreach ($m[0] as $k => $full) {
      $full = $full[0];
      $n1 = trim($m[3][$k][0]);
      $n2 = trim($m[6][$k][0]);
      $var= strlen( $n1 ) == 0 ? $n2 : $n1;

      $s = $m[ 8 ][$k][0];
      $f = $m[ 9 ][$k][0];
      $a = $m[ 5 ][$k][0];
      $p = $m[ 0 ][$k][1];

      $r =  trim($m[7][$k][0]);
      $qs = trim($m[4][$k][0]);
      $parser = $m[1][$k][0];

      if($s == '{' ){
        $co .= $s;
        $m[0][$k][2] = $p + $ofst;
      }

      if($s == '\{' || $f == '\}'){
        $str = substr_replace(  $str, ' ' , $p + $ofst , 1 );
        $co .= '_';
      }

      if( strlen( $var ) > 0 ){
        $ad = ':';
        $exist = array_key_exists( $var , $vrs );

        if ($exist && $r != ';' ) {
          /* ---------------------------------------------------------------- */
          // if the strict mode is enabled trows an error on any empty value
          if ( empty( $vrs[ $var ] ) && $this->strict ) {
            $this->error( $var , VSQL_NULL_FIELD );
          }

          // here we make the substitution
          $nv = $this->parser( $parser , $vrs[ $var ] );
          if ( empty( $nv ) && ($a == '!') ) {
            $this->error( $var , VSQL_NULL_FIELD );
          }
          /* ---------------------------------------------------------------- */
        } else if ($exist && $r == ';' ){
          $nv = '';
        } else if ( strlen( $qs ) > 0 ){
          $nv = $qs;
        } else if ( $cache ) {
          $nv = 'test';
        } else {
          $nv = '';
          $ad = "!";
        }

        $co .= $ad;
        $sub = " {$nv} ";
        $str = substr_replace($str, $sub , $ofst + $p , strlen($full) );
        $ofst += strlen($sub) - strlen($full);
      }

      if($f == '}'){
        $co .= $f;
        $cp = strrpos( $co, '{' );
        $pr = substr(  $co, $cp, strlen( $co ) );
        $pb = $m[ 0 ][ intval( $cp ) ][ 2 ];

        if (strpos($pr, ':') === false) {
          $e = $p + $ofst - $pb + 1;
          $str = substr_replace(  $str, str_repeat(' ', $e ), $pb , $e );
        } else {
          $str = substr_replace(  $str, ' ' , $pb ,        1 );
          $str = substr_replace(  $str, ' ' , $p + $ofst , 1 );
        }

        $co = substr_replace($co, '(' , $cp , 1 );
        $co = substr_replace($co, ')' , strlen($co)-1 , 1 );
      }
    }

    return $str;
  }

//------------------------------------------------ <  parser > ----------------------------------------------------------
  public function parser( $parser , $var ){
    $res =  $this->secure( $var );

    //---------------------- cases ----------------------
    switch ($parser) {
        case 'd':
            settype($var, 'string');
            $res = empty($var) ? "'0000-00-00'": "'{$var}'";
            break;
        case 'email':
            preg_match_all('/(^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$)/', $var , $m );
            $res = "'".$m[0][0]."'";
            break;
        case 'i':
            settype($var, 'int');
            $res = $var;
            break;
        case 'f':
            settype($var, 'float');
            $res = $var;
            break;
        case 'implode':
        case 'array':
            $res = "'" . implode(',', $res ) . "'";
            break;
        case 'json':
            $res = "'" . json_encode($res, JSON_UNESCAPED_UNICODE ) . "'";
            break;
        case 't':
            $res = "'" . trim(strval($res)) . "'";
            break;
        case 's':
            $res = "'". strval($res) . "'";
            break;
    }

    return $res;
  }

//-------------------------------------------- <  modifier > ------------------------------------------------------
  private function modifier( $str , $vrs ) {
      preg_match_all('!\s{1,}(?:as|AS|As|aS)\s{1,}([^,]*)\s{1,}(?:to|TO|tO|To)\s{1,}(\w+),*!', $str, $m );

      foreach ($m[0] as $k => $full) {
        $s = $m[1][$k];
        $f = $m[2][$k];
        $this->fetched[trim($s)] = [trim($f)];
        $str = str_replace($full," AS {$s} ",$str);
      }
      return $str;
  }

//------------------------------------------------ <  get > ------------------------------------------------------------
  public function get( $list = false ) {
      $mysqli = $this->connect;
      $obj = new \stdClass();

      $nr = 0;
      if (mysqli_multi_query($mysqli, $this->vquery)) {
          if ($list) {
              do {

                if($result = mysqli_store_result($mysqli)) {

                  while ($proceso = mysqli_fetch_assoc($result)) {
                      $rt = $this->fetch($result, $proceso);
                      $obj->$nr = $rt;
                      $nr++;
                  }

                  mysqli_free_result($result);
                }

                if (!mysqli_more_results($mysqli)) { break; }
              } while (mysqli_next_result($mysqli) && mysqli_more_results());

          } else {
              $result = mysqli_store_result($mysqli);
              $proceso = mysqli_fetch_assoc($result);
              if($proceso == null){ $obj = null; }
              else { $obj = $this->fetch($result, $proceso); }
          };

      } else {
          $this->error("Fail on query get :" . mysqli_error($mysqli));
      }

      return $obj;
  }

// ------------------------------------------------ <  fetch > ----------------------------------------------------
  private function fetch( $result, $proceso ) {
      $row = new \stdClass();

      $count = 0;
      foreach ($proceso as $key => $value) {
          $direct = $result->fetch_field_direct($count++);
          $ret = $this->_transform_get($value, $direct->type, $key);
          $key = $ret[1];
          $row->$key = $ret[0];
      }

      return $row;
  }

// ------------------------------------------------ <  _transform_get > ------------------------------------------------
  public function _transform_get( $val, $datatype, $key ) {
      $dtypes = array(
          1   => ['tinyint', 'int'],
          2   => ['smallint', 'int'],
          3   => ['int', 'int'],
          4   => ['float', 'float'],
          5   => ['double', 'double'],
          7   => ['timestamp', 'string'],
          8   => ['bigint', 'int'],
          9   => ['mediumint', 'int'],
          10  => ['date', 'string'],
          11  => ['time', 'string'],
          12  => ['datetime', 'string'],
          13  => ['year', 'int'],
          16  => ['bit', 'int'],
          253 => ['varchar', 'string'],
          254 => ['char', 'string'],
          246 => ['decimal', 'float']
      );


      $dt_str = "string";
      if (isset($dtypes[$datatype][1])) {
          $dt_str = $dtypes[$datatype][1];
      }

      settype($val, $dt_str);
      if ($dt_str) {
          $val = utf8_decode(utf8_encode($val));
      }
      settype($val, $dt_str);

      foreach ($this->fetched as $k => $value) {
          if (trim($key) == trim($k)) {
              foreach ($value as $t => $tr) {
                $val = $this->_transform($tr, $val);
      }}}

      return array($val, $key);
    }

// ------------------------------------------------ <  _transform > ----------------------------------------------------
  private function _transform( $transform, $val ) {

      switch ($transform) {
        case 'json':
            $non = json_decode($val,true);
            if ($non != null){
              return $non;
            }
            return json_decode(utf8_decode($val), true);

        case 'array':
            $non = json_decode($val,true);
            if ( $non!=null ){
              return $non;
            }
            return json_decode(utf8_decode($val), true);

        case 'array-std':
            $non = json_decode($val,true);
            if ($non == null ){
              $non = json_decode(utf8_decode($val), true);
            }
            foreach ($non as $key => $value) {
              $non[$key] = (object) $value;
            }
            return $non;
      }

      return $val;
  }


}
