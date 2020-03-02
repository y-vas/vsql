<?php
namespace VSQL\VSQL;

class Cleaner {

  public static function clean($directorio){
    if (substr($directorio, -1) == '/'){
      $directorio = substr($directorio, 0,strlen($directorio)-1);
    }

    $dirs  = scandir($directorio);
    // var_dump($dirs);
    // echo "<hr>";

    foreach ($dirs as $dir) {
      if( $dir == '..' || $dir == '.' ){ continue; }
      $dire = $directorio . DIRECTORY_SEPARATOR . $dir;

      if ( is_dir( $dire ) ){
        self::clean( $dire );
      }else if (file_exists($dire) && substr($dire, -4)=='.php'){
        self::php( $dire );
        echo $dire;
        echo "<hr>";
      }
    }
  }

  private static function pmac($str,$w){
    $vs = array( "}"=>'{', ")"=>'(', "]"=>'[' );
    $s = $vs[$w];

    $c = 1;
    for ($i=0; $i < strlen($str); $i++) {
      $st = strlen($str) - $i;
      $p = $str[$st];
      if ($p == $w) { $c++; }
      if ($p == $s) { $c--; }
      if ($c == 0 ) {return $st;}
    }

    return 0;
  }

  private static function php($file){
    $f = file_get_contents($file);

    // hardcoded globals
    preg_match_all('~\)\s*{[^}[\(]*\bglobal\b\s{1,}[$\w]+;~', $f , $m );
    foreach ($m[0] as $k => $v) {
      preg_match_all('~([^\n\w]*)global\b\s{1,}([$\w]*);~', $v , $m2 );

      $st = "){";
      $tab = $m2[1][0];
      $chunks = array_chunk($m2[2],5);
      foreach ($chunks as $c) {
        $st .= "\n{$tab}global ".implode($c,' ,').";";
      }
      $f = str_replace($v, $st , $f );
    }

    // definitive else
    // preg_match_all('~(?:([{(\[])|([})\]])|([^{}()\[\]\n]*))~', $f , $m , PREG_OFFSET_CAPTURE );
    // foreach ($m[0] as $k => $v) {
    //   $tv = trim($v[0]);
    //   $m[0][$k][2] = $tv;
    //
    //   if( in_array($tv, ['(','[','{'] )){
    //     $co .= $tv;
    //   } elseif( in_array($tv, [')',']'] )){
    //     $co .= $tv;
    //   } elseif($tv == '}'){
    //     $cp = self::pmac($co,'}');
    //
    //     if ($co[$cp-1] == 'e') {
    //       $ss = substr(  $co, 0 , $cp-2 );
    //       $ip = self::pmac($ss,'}');
    //       $ci = strrpos( $ss, 'i' );
    //       $pr = substr($ss, $ci, $ip );
    //
    //       if (strpos($pr, 'f') === false) {
    //         echo "sf";
    //         echo substr(  $f , $m[0][$k][1] , strlen( $ss ) ) . '}<br>';
    //         echo substr(  $co, $cp, strlen( $co ) ) . '}';
    //       }
    //
    //       // if ($ss[$ip-1] == 'i') {
    //       //
    //       // }
    //
    //
    //
    //
    //
    //       // echo substr(  $ss, $ip, strlen( $ss ) ) . '}<br>';
    //       // echo substr(  $co, $cp, strlen( $co ) ) . '}';
    //       echo "<hr>";
    //     }
    //
    //
    //     $co .= $tv;
    //   } elseif( $tv == 'elseif' || $tv == 'else if'){
    //     $co .= 'f';
    //   } elseif( $tv == 'else' ){
    //     $co .= 'e';
    //   } elseif( $tv == 'if' ){
    //     $co .= 'i';
    //   } else {
    //     $co .= "_";
    //   }
    // }
    //
    // echo $co;
    // echo "<hr>";
    // var_dump($m);


    $fp = fopen($file, 'w');
    fwrite($fp, $f );
    fclose($fp);
  }

}
