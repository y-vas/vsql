<?php
namespace VSQL\VSQL;

class Cleaner {

  public static function clean($directorio){
    if (substr($directorio, -1) == '/'){
      $directorio = substr($directorio, 0,strlen($directorio)-1);
    }

    echo "<pre>";

    // $backtrace = debug_backtrace();
    // $index     = end($backtrace);
    // $html      = dirname($index['file']);
    // $root      = dirname($html);
    // $app      = $root . '/App';
    // mkdir( $app, 0777 );
    // var_dump($root);
    // die;

    $dirs  = scandir($directorio);

    foreach ($dirs as $dir) {
      if( $dir == '..' || $dir == '.' ){ continue; }
      $dire = $directorio . DIRECTORY_SEPARATOR . $dir;

      if ( is_dir( $dire ) ){
        self::clean( $dire );
      }else if (file_exists($dire) && substr( $dire, -4 ) == '.php'){
        self::php( $dire );
        echo 'Cleaned : -> ' . $dire;
        echo "<hr>";
        die;
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
      preg_match_all('~([^\n\w]*)global\b\s{1,}([$\w, ]*);~', $v , $m2 );

      $st = "){";
      $tab = $m2[1][0];

      $chunks = [];
      foreach ($m2[2] as $vc) {
        $exe = [];
        foreach (explode(',',$vc) as $ex) {
          $exe[] = trim($ex);
        }
        $chunks = array_merge($chunks,$exe);
      }
      $chunks = array_chunk($chunks,5);

      foreach ($chunks as $c) {
        $st .= "\n{$tab}global ".implode($c,' ,').";";
      }
      $f = str_replace($v, $st , $f );

    }




    $nf = self::ifs($f);

    $fp = fopen($file, 'w');
    fwrite($fp, $nf );
    fclose($fp);

  }


  private static function ifs($f){
    $nf = $f;
    // definitive else
    preg_match_all('~(?:([{(\[])|([})\]])|([^{}()\[\]\n]*))~', $f , $m , PREG_OFFSET_CAPTURE );
    foreach ($m[0] as $k => $v) {
      $tv = trim($v[0]);
      $m[0][$k][2] = $tv;

      if( in_array($tv, ['(','[','{'] )){
        $co .= $tv;
      } elseif( in_array($tv, [')',']'] )){
        $co .= $tv;
      } elseif($tv == '}'){

        $cp = self::pmac( $co , '}' );
        $lcp = $cp - 1;
        $lc = $co[ $lcp ];
        $ss = substr(  $co, 0 , $cp - 2 );

        // start conditioning
        if ($lc == 'e') {
          $ip = self::pmac($ss, '}' );

          $ci = strrpos( $ss, 'i' );
          $pr = substr($ss, $ci, $ip );

          if (strpos($pr, 'f') === false) {
            $start = $m[0][$ip][1];
            $end = $m[0][strlen($ss)][1];

            $lstart = $m[0][$cp][1];
            $lend = $m[0][$k][1];

            $v1 = trim(substr(  $f , $start+1 , $end - $start -1 ));
            $v2 = trim(substr(  $f , $lstart+1 , $lend - $lstart -1 ));

            $cc1 = count(explode(';',$v1));
            $cc2 = count(explode(';',$v2));

            $c1 = trim(explode('=',$v1)[0]);
            $c2 = trim(explode('=',$v2)[0]);
            //
            // echo "<hr>";
            // echo substr(  $f , $m[0][$ci][1] , $lend - $m[0][$ci][1] +1  );
            // echo "<hr>";
            // echo "$co";
            // echo "<br><br><br><br><br><br><br><br><br><br><br><br><br><br>";
            // echo "$v1 <br> $v2 ";
            // echo "<hr>";
            // echo "$cc1 != $cc2";
            //
            // echo "<hr>";
            // var_dump(explode(';',$v1));
            // var_dump(explode(';',$v2));
            // echo "<hr>";


            if ($cc1 != $cc2) {
              continue;
            }

            if (strpos($v1, '//') !== false || strpos($v1, '/*') !== false) {
              continue;
            }

            if (strpos($v2, '//') !== false || strpos($v2, '/*') !== false) {
              continue;
            }

            if ($c1 == $c2 && $cc1 == 2 && $cc2 == 2) {
              $css = $m[0][$ci+1][1];
              $send = $m[0][$ip][1];
              $cond = substr(  $f , $css, $send - $css -1 );
              $replace = substr(  $f , $m[0][$ci][1] , $lend - $m[0][$ci][1] +1  );

              $f1 = trim(str_replace(';','',implode('=',array_slice(explode('=',$v1),1))));
              $f2 = trim(str_replace(';','',implode('=',array_slice(explode('=',$v2),1))));

              $shortif = $c1 ." = ". $cond . " ? " . $f1 .' : '. $f2 . ';';

              if (strlen($shortif) <= 120) {
                $nf = str_replace(trim($replace), $shortif , $nf );
              }

            }
          }
        }

        //  else if ($lc == ')') {
        //   $cs = self::pmac( $ss , ')' );
        //
        //   // starter
        //   $sc = $co[ $cs - 1 ];
        //
        //   // single if
        //   if ($sc == 'i'){
        //
        //   }
        //
        // }



        $co .= $tv;
      } elseif( $tv == 'elseif' || $tv == 'else if'){
        $co .= 'f';
      } elseif( $tv == 'else' ){
        $co .= 'e';
      } elseif( $tv == 'if' ){
        $co .= 'i';
      } else {
        $co .= "_";
      }
    }

    return $nf;
  }

}
