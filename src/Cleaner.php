<?php
namespace VSQL\VSQL;

class Cleaner {

  public static function clean($directorio){
    $dirs  = scandir($directorio);

    foreach ($dirs as $dir) {
      if( $dir == '..' || $dir == '.' ){ continue; }

      self::php( $directorio . DIRECTORY_SEPARATOR . $dir );


      if (is_dir($dir)){
        self::clean($dir);
      }
    }

  }

  private static function balance($str,$end){

  }

  private static function php($file){
    $f = file_get_contents($file);

    // hardcoded globals
    preg_match_all('~\)\s*{[^}]*\bglobal\b\s{1,}[$\w]+;~', $f , $m );
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

    // // definitive else
    // preg_match_all('~\sif\s*\(~', $f , $m , PREG_OFFSET_CAPTURE );
    // foreach ($m[0] as $k => $v) {
    //   var_dump($v);
    //   echo "<hr>";
    // }




    $fp = fopen($file, 'w');
    fwrite($fp, $f );
    fclose($fp);
  }

}
