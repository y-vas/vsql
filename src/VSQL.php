<?php

namespace VSQL\VSQL;
include(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'DB.php');

//                                           ██╗     ██╗ ███████╗  ██████╗  ██╗
//                                           ██║    ██║ ██╔════╝ ██╔═══██╗ ██║
//                                           ██║   ██║ ███████╗ ██║   ██║ ██║
//                                          ╚██╗  ██║ ╚════██║ ██║▄▄ ██║ ██║
//                                           ╚████╔╝  ███████║╚ ██████║ ███████╗
//                                             ╚═══╝   ╚══════╝ ╚══▀▀═╝ ╚══════╝

class VSQL extends \DB {

  public function __construct( $id = null ){
    $this->connect();
  }

//------------------------------------------------ <  query > ----------------------------------------------------------
  public function query($str , $vrs , $debug = false ) {
    $this->query  = $str;
    $this->vquery = ''; // init again
    $this->vars   = $vrs;

    $str = $this->interpreter( $str, $vrs );
    $str = $this->modifier( $str, $vrs );

    $this->vquery = $str;

    if ( $debug ){
      $this->error( 'Inspect' , 0 , true );
    }

    return $this->vquery;
  }

  public function run(){

      $mysqli = $this->connect;
      $mysqli->query(
          $this->vquery
      );

      return $mysqli;
  }

//------------------------------------------------ <  interpreter > ----------------------------------------------------------
// str = query string you pass to interpret the query
// vrs = values to use
// cache = deprecated
// tring to make this with c++ to make the interpreter faster
  protected function interpreter( $str, $vrs ) {
    // check how the regex match works here
    // https://regex101.com/r/b65mwz/1/

    preg_match_all('~(?:([^\s,=%]*)([:])(\w+)[^\S\r\n]*(?(?=\?)\?([^;]*);|([!;]*))|([^\s{\)]*)(;)|(\\\\{0,1}{)|(\\\\{0,1}})|(default\s{0,1}:))~',
                  $str, $m , PREG_OFFSET_CAPTURE );

    $ofst = 0; // offset lenght

    // $co contains the the brakets and substitution fields positions
    // and show's if the brakts have been closed
    $co = '';

    $switches = [];
    foreach ( $m[0] as $k => $full ) {
      // full match
      $full = $full[ 0 ];

      // position
      $p = $m[ 0 ][$k][1] ?? null;

      // the key to replace
      $n1 = isset($m[ 3 ][$k][0]) ? trim($m[ 3 ][$k][0]) : null;
      $n2 = isset($m[ 6 ][$k][0]) ? trim($m[ 6 ][$k][0]) : null;
      $var= strlen( $n1 ) == 0 ? $n2 : $n1;

      // checks for '{' // s = start of block
      $s = $m[ 8 ][$k][0] ?? null;
      // checks for '}' f = final of block
      $f = $m[ 9 ][$k][0] ?? null;

      // checks if field is requiered
      $a = $m[ 5 ][$k][0] ?? null;

      $r  = isset($m[ 7 ][$k][0]) ? trim($m[ 7 ][$k][0]) : null;
      $qs = isset($m[ 4 ][$k][0]) ? trim($m[ 4 ][$k][0]) : null;

      // parser
      $parser = $m[ 1 ][$k][0] ?? null;

      // defaault value if is set
      $default = $m[ 10][$k][0] ?? null;

      if($s == '{' ){
        $co .= $s;
        $m[0][$k][2] = $p + $ofst;
      }

      if(!empty( $default )){
        $co .= '~';
      }

      if($s == '\{' || $f == '\}'){
        $str = substr_replace( $str , ' ' , $p + $ofst , 1 );
        $co .= '_';
      }

      if( strlen( $var ) > 0 ){
        $ad = ':';
        $exist = array_key_exists( $var , $vrs );

        if ( $exist && $r != ';' ){
          // here we make the substitution

          $nv = $this->parser( $parser , $vrs[ $var ] );

          if ( $nv === null ){
            $ad = "!";
            if ( strlen( $qs ) > 0 ){ $nv = $qs; }
          }

          /* ---------------------------------------------------------------- */
        } elseif( $exist && $r == ';' ){
          $ad = '*';
          $nv = '/**/';
        } else if ( strlen( $qs ) > 0 ){
          $nv = $qs;
        } else {
          $nv = '';
          $ad = "!";
        }

        $co .= $ad;
        $sub = $nv;
        $str = substr_replace($str, $sub , $ofst + $p , strlen($full) );
        $ofst += strlen( $sub ) - strlen( $full );
      }

      // echo $f;

      if($f == '}'){
        $co .= $f;
        $cp = strrpos( $co, '{' );
        $pr = substr(  $co, $cp, strlen( $co ) );

        $pb = $m[ 0 ][ intval( $cp ) ][ 2 ];
        // echo $pr;
        // echo '<hr>';

        $e = $p + $ofst - $pb + 1;


        // if { default: } is set
        if (strpos( $pr,'~') !== false) {
          $grp = substr(  $str , $pb + 1 , $e - 2 );
          $exp = explode('default:', $grp );

          // if (($vrs['find'] ?? null) != null) {
          //   dd(  $pr );
          // }

          if (strpos($pr, ':') !== false ||
              strpos($pr, '*') !== false
            ) {
            $nst = str_repeat(' ', strlen( $exp[1] ) + 10) . $exp[0];
            $str = substr_replace(  $str, $nst, $pb , $e );
          } else if (strpos($pr, ':') === false) {
            // we add 10 ' ' because the lenght of $exp
            $nst = str_repeat(' ',strlen($exp[0]) + 10) . $exp[1];
            $str = substr_replace(  $str, $nst, $pb , $e );
          }


        }else if ( strpos( $pr,'*') ) {

          // if has a key;
          $str = substr_replace(  $str, ' ' , $pb ,        1 );
          $str = substr_replace(  $str, ' ' , $p + $ofst , 1 );

        }else if ( strpos( $pr,'!') !== false && strpos($pr, ':') !== false) {
          // has negatives and positives trader_cdl3inside
          // clear it
          $str = substr_replace(  $str, str_repeat(' ', $e ), $pb , $e );
        } else if (strpos($pr, ':') === false) {
          // negative
          $str = substr_replace(  $str, str_repeat(' ', $e ), $pb , $e );
        } else {
          // positive
          $str = substr_replace(  $str, ' ' , $pb ,        1 );
          $str = substr_replace(  $str, ' ' , $p + $ofst , 1 );
        }

        // echo "<hr>";
        // echo $co;
        // echo "<hr>";
        // echo $co;
        // die;

        if ($pr == '!') {
          $co = substr_replace($co, 'i' , $cp+1 , 1 );
        }

        $co = substr_replace($co, '(' , $cp , 1 );
        $co = substr_replace($co, ')' , strlen( $co )-1 , 1 );
      }
    }

    // echo $co;
    // echo "<hr>";
    // echo "end";
    // echo "<hr>";
    return $str;
  }

//------------------------------------------------ <  parser > ----------------------------------------------------------
// parser = parser value ex: d , email , +i
// var = the variable to parse
  public function parser( $parser , $var ){
    // secure the value before inserting it in the query
    $res = $this->secure( $var );

    //---------------------- cases ----------------------
    switch ( $parser ) {
      // d = date
      case 'd':
          settype($var, 'string');
          $res = empty($var) ? "'1970-01-01'": "'{$var}'";
          if (!(\DateTime::createFromFormat('Y-m-d', $var) !== false)) {
              $res = null;
          }
          break;

      case 'dt':
          settype($var, 'string');
          $res = empty($var) ? "'1970-01-01 00:00:00'": "'{$var}'";
          if (!(\DateTime::createFromFormat('Y-m-d H:i:s', $var) !== false)) {
              $res = null;
          }
          break;

      case 'email':
          if (!filter_var($res, FILTER_VALIDATE_EMAIL)) {
            $res = null;
          } else {
            $res = "'".$res."'";
          }

          break;

      // i = integer
      case 'i':
          if ($var === null){
            $res = null;
          } elseif (!is_numeric($var)) {
            $res = null;
          }else{
            settype($var, 'int');
            $res = $var;
          }
          break;

      // parse to positive integer
      case 'r':
      case '+i':
          if ($var === null){
            $res = null;
          } elseif (!is_numeric($var)) {
            $res = null;
          }else{
            settype($var, 'int');
            $res = abs($var);
          }
          break;

      // parse to float
      case 'f':
          if ($var === null){
            $res = null;
          } elseif (!is_numeric($var)) {
            $res = null;
          }else{
            settype($var, 'float');
            $res = $var;
          }

          break;

      // parse to positive float
      case '+f':
          if ($var === null){
            $res = null;
          } elseif (!is_numeric($var)) {
            $res = null;
          }else{
            settype($var, 'float');
            $res = abs($var);
          }
          break;

      // implode the array
      case 'implode':
      case 'array':
          if (!is_array( $res )) {
            $res = [];
          }
          $res = "'" . implode(',' ,  $res ) . "'";
          break;

      case 'c':
          if (!is_array( $res )) {
            $res = null;
            break;
          }
          $res = implode(',' ,  $res );
          break;

      // transform the valie to json
      case 'json':
          $res = "'" . json_encode($res, JSON_UNESCAPED_UNICODE ) . "'";
          break;

      // trim the value
      case 't':
          $res = "'" . trim(strval( $res )) . "'";
          break;

      case '"': // add for quoted values
          $res =  '"' . trim(strval( $res ));
          break;
      case '\'': // add for quoted values
          $res =  "'" . trim(strval( $res ));
          break;

      // parse the value to string
      case 's':
          $v = strval($res);
          $res = (strlen($v) > 0) ? "'". $v . "'": null;
          break;

      case 'q':
          $v = strval($res);
          $res = (strlen($v) > 0) ? '"'. $v . '"': null;
          break;

      case 'raw':
          json_decode( $var );
          $res = (json_last_error() == JSON_ERROR_NONE) ? "'". $var."'" : '{}';
          break;

      case 'checkbox':
          $res = ($var == 'on') ? 1 : 0;
          break;

      case 'rstr':
          $res = substr(md5(mt_rand()),0,7);
          break;

      default:
          $v = strval($res);
          $res = (strlen($v) > 0) ? $v : null;
          break;
    }

    return $res;
  }

//-------------------------------------------- <  modifier > ------------------------------------------------------
// the modifier saves how the data sould be fetched once the data is fetched
  private function modifier( $str , $vrs ) {
      preg_match_all('!\s{1,}(?:as|AS)\s{1,}([^,]*)\s{1,}(?:to|TO)\s{1,}(\w+),*!', $str, $m );

      foreach ($m[0] as $k => $full) {
        $s = $m[1][$k];
        $f = $m[2][$k];
        $this->fetched[ trim( $s )] = [trim($f)];
        $str = str_replace($full," AS {$s} ",$str);
      }

      return $str;
  }

//------------------------------------------------ <  get > ------------------------------------------------------------
  public function get( $list = false ) {
      if ($list === 'output-query') {
        return $this->vquery;
      }

      $mysqli = $this->connect;
      $obj    = new \stdClass();
      $count  = 0;

      if (mysqli_multi_query( $mysqli, $this->vquery )) {

          // to remove for for making csv's
          if ($list === 'output-csv') {
              //----------------------------------------------------------------
              $fp = fopen('php://output', 'wb');
              do { if( $result = mysqli_store_result($mysqli) ){
                  while( $proceso = mysqli_fetch_assoc( $result ) ){
                    $crow = ( array ) $this->fetch( $result, $proceso );

                    if ( $count == 0 ){
                      fputcsv( $fp , array_keys($crow) );
                    }

                    fputcsv( $fp , $crow );
                    $count ++;
                  }

                  mysqli_free_result($result);
              }

              if (!mysqli_more_results($mysqli)) { break; }
              } while (mysqli_next_result($mysqli) && mysqli_more_results());

              fclose( $fp );
              return $fp;

          } elseif ($list === true) {

              //----------------------------------------------------------------
              do { if($result = mysqli_store_result($mysqli) ) {
                  while ($proceso = mysqli_fetch_assoc($result)) {
                      $obj->$count = $this->fetch($result, $proceso);
                      $count++;
                  }
                  mysqli_free_result($result);
                }
              if (!mysqli_more_results($mysqli)) { break; }
              } while (mysqli_next_result($mysqli) && mysqli_more_results() );

          } else {
              $result = mysqli_store_result($mysqli);
              if ( !$result ) {
                $this->error("Fail on query get :" . mysqli_error($mysqli));
              }

              $proceso = mysqli_fetch_assoc($result);
              if($proceso == null){ $obj = null; }
              else { $obj = $this->fetch( $result, $proceso ); }
          };

      } else {
        $this->error("Fail on query get :" . mysqli_error($mysqli));
      }

      return $obj;
  }


// ------------------------------------------------ <  fetch > ----------------------------------------------------
  private function fetch( $result, $proceso , $colname = false ) {
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
  public function _transform_get( $val, $datatype, $key ){

      foreach ($this->fetched as $k => $value) {
          if (trim( $key ) == trim( $k )){
              foreach ( $value as $t => $tr ){
                $val = $this->_transform( $tr, $val );
      }}}

      return array( $val, $key );
    }

// ------------------------------------------------ <  _transform > ----------------------------------------------------
  private function _transform( $transform, $val ) {
      if (isset($_ENV['VSQL_UTF8']) && $_ENV['VSQL_UTF8'] == true) {
          $val = utf8_decode( $val );
      }

      switch ( $transform ) {
        case 'json':
            $non = json_decode( $val , true );
            if ( $non != null ){ return $non; }

            $non = json_decode( utf8_encode( $val ), true );

            if ( $non != null ){
              return $non;
            }

            return json_decode($val, true);
        case 'array':
            $non = json_decode($val,true);
            if ( $non != null ){
              return $non;
            }
            return json_decode($val, true);
        case 'explode':
            return explode( ',' , $val );
        case 'array-std':
            $non = json_decode( $val , true );
            if ( $non == null ){
              $non = json_decode( $val, true );
            }
            foreach ( $non as $key => $value ){
              $non[$key] = (object) $value;
            }
            return $non;
        default:
          break;
      }

      return $val;
  }

}
