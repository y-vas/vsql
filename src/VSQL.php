<?php

namespace VSQL\VSQL;

//
//                                           ██╗     ██╗███████╗ ██████╗ ██╗
//                                           ██║    ██║██╔════╝██╔═══██╗██║
//                                           ██║   ██║███████╗██║   ██║██║
//                                          ╚██╗ ██╔╝╚════██║██║▄▄ ██║██║
//                                           ╚████╔╝ ███████║╚██████╔╝███████╗
//                                             ╚═══╝  ╚══════╝ ╚══▀▀═╝ ╚══════╝
//

class ExVSQL extends \Exception { }

class VSQL {

  private $CONN = null;
  private $query_vars = array();
  private $query_string = "";
  private $query_original = "";
  private $trows_exteption = "default";
  private $concat_name = false;
  private $_transformed = array();
  public $id = '';

//------------------------------------------------ <  _construct > -----------------------------------------------------
  function __construct($id = "", $exception = "default") {
    $this->trows_exteption = $exception;

    foreach (array('servername','username','password','database') as $value) {
      if (!isset($_ENV["vsql_".$value])) {
        $this->_error_msg("Enviroment value < \$_ENV['vsql_".$value."'] > is not set!");
      }
    }

    if(!empty($id)){
      $this->id = $id;
      if (empty($_ENV["vsql_db_$id"])) {
        $_ENV["vsql_db_$id"] = self::_conn();
      }

      $this->CONN = $_ENV["vsql_db_$id"];
    }else {
      $this->CONN = self::_conn();
    }

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
      $_ENV["vsql_servername"],
      $_ENV["vsql_username"],
      $_ENV["vsql_password"],
      $_ENV["vsql_database"]
    );
  }

//------------------------------------------------ <  TRANSACTION > ----------------------------------------------------
  // public static function START_TRANSACTION(string $var, $config = array()) : VSQL {
  //
  //   if(!empty($_ENV["vsql_db_$var"])){
  //     return $_ENV["vsql_db_$var"];
  //   }
  //
  //   $CONN = new VSQL();
  //   $config["exceptions"] = !isset($config["exceptions"]) ? false   : $config["exceptions"];
  //   $CONN->config($config);
  //   $CONN->is_transaction = true;
  //   $CONN->query("START TRANSACTION;", array() );
  //   $CONN->run();
  //
  //   $_ENV["vsql_db_$var"] = $CONN;
  //   return $CONN;
  // }
  // public static function END_TRANSACTION($name, $show = false)  : bool {
  //
  //   $vsql = new VSQL($name);
  //
  //   $yep = true;
  //   $err = "";
  //   foreach ($_ENV['VSQL_LOGS'] as $key => $value) {
  //     if ($show) { $err = "<br>" . $value . "<br>". $err; }
  //     if (!empty($value)) { $yep = false; }
  //   }
  //
  //   if ($show && !$yep) {
  //     $vsql->_error_msg( $err );
  //   }
  //
  //   $vsql->query("COMMIT;", array() );
  //   $vsql->run();
  //
  //   $_ENV["vsql_db_$name"] = null;
  //
  //   return $yep;
  // }
//------------------------------------------------ <  _error_msg > -----------------------------------------------------
  public function _error_msg($error_msg) {

    if ($this->trows_exteption == 'pretty') {
      self::_show_example("<div>".$error_msg."</div>");
    }

    if ($this->trows_exteption == 'default') {
      throw new \Exception("Error :" . $error_msg );
    }

    throw new ExVSQL("Error :". $error_msg);
  }

//------------------------------------------------ <  query > ----------------------------------------------------------
  public function query(string $query_string, array $query_vars, $debug = "") : string {
    $this->query_original = $query_string;
    $this->query_vars = $query_vars;
    $this->query_string = $query_string;

    $cache = $this->_cache();

    if(empty($cache)){
      $query_string = $this->_find_objects($query_string);
      $query_string = $this->_quote_check($query_string);
      $query_string = $this->_var_transform($query_string);
      $this->query_string = $query_string;
    }else {
      $query_string = $this->_var_transform($cache);
      $this->query_string = $query_string;
    }

    $this->_inspect($debug);
    return $query_string;
  }

//------------------------------------------------ <  _inspect > -------------------------------------------------------
  private function _inspect($debug){
    $this->trows_exteption = "pretty";

    $extra = '';
    if (strpos($debug, ':') !== false){
      $p = explode(":", $debug);
      $debug = $p[0];
      $extra = $p;
    }

    switch ($debug) {
      case 'show':
      case 'debug':
        $this->_error_msg(" DEBUG ");
        break;

      case 'dump_get':
        ob_start();
        var_dump($this->get(($extra[1] == "all")));
        $result = ob_get_clean();
        $this->_error_msg("<strong>VAR DUMP</strong> : <br> <code class='scss'> $result </code>");
        break;

      case 'mk_funk':
        $this->_mkfunction($extra[1],$extra[2]);
        break;
    }
  }

//-------------------------------------------- <  _find_objects > ------------------------------------------------------
  private function _find_objects($query_string){
    preg_match_all('!(\w*?)_VSQL\((\X*)!', $query_string, $match );

    while (count($match[0]) != 0) {

      $ad = 1;
      $str = "";
      for ($l=0; $l < strlen($match[2][0]); $l++) {
        $lt = $match[2][0][$l];
        if ($ad == 0){ $str = substr($str, 0, -1); break; }
        if ($lt == ")"){ $ad--; }
        if ($lt == "("){ $ad++; }
        $str = $str . $lt;
      }

      preg_match_all('!(\w*?)_VSQL\(\Q' .$str. '\E\)(?:\s*?\s*\w{2}\s*(\w*)\s*)?!', $query_string, $match );

      foreach ($match[2] as $key => $value) {
        $replace = $this->_vsql_function($match[1][$key],$str,$match[2][$key]);
        $query_string = str_replace($match[0][$key], $replace , $query_string);
      }

      preg_match_all("!(\w*?)_VSQL\((\X*)!", $query_string, $match);
    }

    return $query_string;
  }

//-------------------------------------------- <  _vsql_function > -----------------------------------------------------
  private function _vsql_function($func, $vals, $name){
      $lname = "";
      if (!empty($name)) {
        $lname = " AS $name \n\n";
      }

      $vals = preg_replace('![^<](=>)!', ',' , $vals );

      switch ($func) {
        case 'STD':
          $this->_transformed[$name] = ['json'];
          return 'JSON_OBJECT(' . $vals . ')'. $lname;
          break;

        case 'JGET':
          $this->_transformed[$name] = ['json'];

          $vales = explode(",", $vals);
          if(count($vales) != 2) {
            $this->_error_msg('JGET_VSQL('.$vals.') : Requieres only 2 values !');
          }

          $v1 = trim($vales[0]);
          $v2 = trim($vales[1]);

          return "(SELECT IF (JSON_VALID($v1),JSON_UNQUOTE(JSON_EXTRACT($v1,'$.$v2')), NULL))". $lname;
          break;

        case 'ARRAY':
          $this->_transformed[$name] = ['array'];
          return 'JSON_OBJECT(' . $vals . ')'. $lname;
          break;

        case 'TO_STD':
          $this->_transformed[$name] = ['json'];
          return '(' . $vals . ')'. $lname;
          break;

        case 'TO_ARRAY':
          $this->_transformed[$name] = ['array'];
          return '(' . $vals . ')'. $lname;
          break;
      }

    return  "";
  }

//------------------------------------------- <  _var_transform > ------------------------------------------------------
  private function _var_transform(string $query_string, $return_empty_if_has_null_values = false) : string {
    preg_match_all('!<(.*?)?(\!)?:(.*?)>!', $query_string, $match );

    foreach ($match[1] as $key => $simbol) {
      $var_key = $match[3][$key];
      $not_null = $match[2][$key];

      $var = $this->_convert_var($simbol, $var_key);

      if ($var == null) {

        if ($not_null == "!") {
          $this->_error_msg("$var_key key resulted in null!");
        }

        if ($return_empty_if_has_null_values) {
          return "";
        }

      }

      $query_string = str_replace($match[0][$key], $var, $query_string);
    }

    return $query_string;
  }

//--------------------------------------------- <  _quote_check > ------------------------------------------------------
  private function _quote_check(string $query_string, $cache = false ) : string {
    preg_match_all("!{{([\w*?:]*)([^{{]*?)}}!", $query_string, $match_brakets);

    while (count($match_brakets[0]) != 0) {

      foreach ($match_brakets[2] as $key => $value) {


        $tags = explode(':',$match_brakets[1][$key]);
        if (count($tags) > 1) {
          $show = false;

          foreach ($tags as $h => $t) {
            if(isset($this->query_vars[$t])){
              $show = true;
              break;
            }
          }

          if ($show == false) { $value = ""; }
        }

        $res = $this->_var_transform($value , true);
        if($cache && !empty($res)){
          $res = $value;
        }
        $query_string = str_replace($match_brakets[0][$key], $res, $query_string);

      }

      preg_match_all("!{{([\w*?:]*)([^{{]*?)}}!", $query_string, $match_brakets);
    }


    return $query_string;
  }

//------------------------------------------------ <  _get_var_from_query_vars > ---------------------------------------
  private function _qvar(string $var) {
    return empty($this->query_vars[$var]) ? null : $this->query_vars[$var];
  }

//------------------------------------------------ <  _get_var_from_query_vars > ---------------------------------------
  private function _tvar(string $var) {
    return empty($this->tags[$var]) ? null : $this->tags[$var];
  }

//------------------------------------------------ <  _convert_var > ---------------------------------------------------
  private function _convert_var(string $type, string $var){

    $result = null;
    //---------------------- cases -----------------
		switch ($type) {
      // if is empty does nothing only paste the value
      case '':
        $result = $this->_ecape_qvar($var);
        break;

      // @E = fetch value from $ENV
      case '@e':
      case '@E':
        $result = empty($_ENV[$var]) ? null: $_ENV[$var];
        $result = $this->_sql_escape($result);
        break;

      // @E = fetch value from $_COOKIE
      case '@c':
      case '@C':
        $result = empty($_COOKIE[$var]) ? null: $_COOKIE[$var];
        $result = $this->_sql_escape($result);
        break;

      // @E = fetch value from $_SESSION
      case '@s':
      case '@S':
        $result = empty($_SESSION[$var]) ? null: $_SESSION[$var];
        $result = $this->_sql_escape($result);
        break;

      // cast to integer
      case 'i':
      case 'I':
        $x = $this->_qvar($var);
        if($x != null){
          settype($x, 'integer');
          $result = $this->_sql_escape($x);
        }
        break;

      // cast to float
      case 'f':
      case 'F':
        $x = $this->_qvar($var);
        settype($x, 'float');
        $result = $this->_sql_escape($x);
        break;

      // implode the array
      case 'implode':
        $x = $this->_qvar($var);
        $res = $this->_sql_escape(implode(',', $x));
        $result = $res != null ? "'".$res."'" : $res;
        break;

      // trims the value
      case 't':
      case 'T':
        $result = $this->_sql_escape(trim($this->_qvar($var)));
        break;

      // transforms the value to string
      case 's':
      case 'S':
        $res = $this->_sql_escape(trim($this->_qvar($var)));
        $result = $res != null ? "'".$res."'" : $res;
        break;

    }

    return $result;
	}

//------------------------------------------------ <  _sql_escape > ----------------------------------------------------
  private function _sql_escape($var){
    if(is_array($var)) {
    	foreach($var as $_element) {
    		$_newvar[] = $this->_sql_escape($_element);
    	}
    	return $_newvar;
    }

    if(function_exists('mysql_real_escape_string')) {
    	if(!isset($this->CONN)) {
    		return mysql_real_escape_string($var);
    	} else {
    		return mysql_real_escape_string($var, $this->CONN);
    	}
    } elseif(function_exists('mysql_escape_string')) {
    	return mysql_escape_string($var);
    } else {
    	return addslashes($var);
    }
	}

//------------------------------------------------ <  _ecape_qvar > ----------------------------------------------------
  private function _ecape_qvar(string $var) {
    return $this->_sql_escape($this->_qvar($var));
  }

//------------------------------------------------ <  get > ------------------------------------------------------------
  public function get($list = false, $type = "array") {
    $mysqli = $this->CONN;
    $obj = null;

    if ($type == "array") {
      $obj = array();
    }else {
      $obj = new \stdClass();
    }

    $nr = 0;
    if (mysqli_multi_query($mysqli, $this->query_string)) {
        if ($list) {
          do {
            if ($result = mysqli_store_result($mysqli)) {

              while ($proceso = mysqli_fetch_assoc($result)) {
                $rt = $this->_fetch_row($result, $proceso);
                if ($type == "array"){ $obj[$nr] = $rt; }
                else { $obj->$nr = $rt; }
                $nr ++;
              }

              mysqli_free_result($result);
            }

            if(!mysqli_more_results($mysqli)){
              break;
            }

          } while (mysqli_next_result($mysqli) && mysqli_more_results());

        }else {
          $result = mysqli_store_result($mysqli);
          $proceso = mysqli_fetch_assoc($result);
          $obj = $this->_fetch_row($result, $proceso);
        };

    }else {
      $this->_error_msg("Fail on query get :" . mysqli_error($mysqli) );
    }
    return $obj;
  }

//------------------------------------------------ <  run > ------------------------------------------------------------
  public function run($list = false) {
    $mysqli = $this->CONN;

    $obj = new \stdClass();
    $nr = 0;
    mysqli_multi_query($mysqli, $this->query_string);

    if ($list) {

      while(mysqli_more_results($mysqli)){
          mysqli_next_result($mysqli);

          if ($result = mysqli_store_result($mysqli)) {
            while ($proceso = mysqli_fetch_assoc($result)) {
                $obj->$nr = $this->_fetch_row($result, $proceso);
                $nr ++;
            }
            mysqli_free_result($result);
          }
      }

    }else {
      $result = mysqli_store_result($mysqli);
      $proceso = mysqli_fetch_assoc($result);
      $obj = $this->_fetch_row($result, $proceso);
    };

    if(mysqli_error($mysqli)){
      $this->_error_msg(mysqli_error($mysqli));
    }

    return $obj;
  }

// ------------------------------------------------ <  _fetch_row > ----------------------------------------------------
  private function _fetch_row($result, $proceso) : \stdClass {
    $row = new \stdClass();

    $count = 0;
    foreach ($proceso as $key => $value) {
      $direct = $result->fetch_field_direct($count);
      $ret = $this->_transform_get($value, $direct->type , $key);
      $key = $ret[1];

      if ($this->concat_name == true) {
        $key = $direct->orgtable."__".$key;
      }

      $row->$key = $ret[0];
      $count++;
    }

    return $row;
  }

// ------------------------------------------------ <  _transform_get > ------------------------------------------------
  public function _transform_get($val, string $datatype, string $key) {

    $mysql_data_type_hash = array(
        1   =>array('tinyint','int'),
        2   =>array('smallint','int'),
        3   =>array('int','int'),
        4   =>array('float','float'),
        5   =>array('double','double'),
        7   =>array('timestamp','string'),
        8   =>array('bigint','int'),
        9   =>array('mediumint','int'),
        10  =>array('date','string'),
        11  =>array('time','string'),
        12  =>array('datetime','string'),
        13  =>array('year','int'),
        16  =>array('bit','int'),
        253 =>array('varchar','string'),
        254 =>array('char','string'),
        246 =>array('decimal','float')
    );



    $dt_str = "string";
    if (isset($mysql_data_type_hash[$datatype][1])){
      $dt_str = $mysql_data_type_hash[$datatype][1];
    }

    settype($val, $dt_str);

    foreach ($this->_transformed as $k => $value) {
      if(trim($key) == trim($k)){
        foreach ($value as $t => $tr) {
          $val = $this->_transform($tr,$val);
        }

      }
    }

    return array( $val, $key );
  }

// ------------------------------------------------ <  _transform > ----------------------------------------------------
  private function _transform($transform, $val){

    switch ($transform) {
      case 'json':
        return (object) json_decode(utf8_decode($val),true);

      case 'array':
        return json_decode(utf8_decode($val),true);

    }

    return $val;
  }

// ------------------------------------------------ <  _show_example > -------------------------------------------------
  public function _show_example($error = "") {
    echo "
    <!DOCTYPE html>
    <html lang=\"en\">
    <head>
      <title>VSQL INFO</title>
      <meta charset=\"utf-8\">
      <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
      <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css\">
      <script src=\"https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js\"></script>
      <script src=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/js/bootstrap.min.js\"></script>
      <script src=\"https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.4/clipboard.min.js\"></script>
      <link rel=\"stylesheet\" href=\"//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.15.10/styles/default.min.css\">
      <script src=\"//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.15.10/highlight.min.js\"></script>
      <script>hljs.initHighlightingOnLoad();</script>
    </head>
    <body>

    <div class=\"jumbotron text-center\"> <h1>VSQL INFO</h1> </div>

    <div class=\"container\" style=\"line-height:70%;\">

      <h2>ERROR INFO</h2>
      <pre>$error</pre>
      <div class=\"row\">
        <div class=\"col-sm-6\">
        <button class='btn float-right' onclick=\"copyToClipboard('.m2')\">Copy</button>
          <pre><code class='sql'>ORIGINAL<hr><div class='m2'>" . htmlentities($this->query_original) . "</div></code></pre>
        </div>
        <div class=\"col-sm-6\">
        <button class='btn float-right' onclick=\"copyToClipboard('.m1')\">Copy</button>
          <pre><code class='sql'>TRANSFORMED<hr><div class='m1'>" . htmlentities($this->query_string) . "</div></code></pre>
        </div>
      </div>
      <div class=\"row\">
        <div class=\"col-sm-12\">
          <h2>INITIALIZE</h2>
          <p>Set on the SUPERGLOBAL <strong>\$_ENV</strong> your db values: (example) </p>
          <p><b class=\"text-danger\">\$_ENV</b>[<b class=\"text-success\">\"vsql_servername\"</b>] = <b class=\"text-success\">\"127.0.0.1\";</b></p>
          <p><b class=\"text-danger\">\$_ENV</b>[<b class=\"text-success\">\"vsql_username\"</b>] = <b class=\"text-success\">\"root\";</b></p>
          <p><b class=\"text-danger\">\$_ENV</b>[<b class=\"text-success\">\"vsql_password\"</b>] = <b class=\"text-success\">\"password\";</b></p>
          <p><b class=\"text-danger\">\$_ENV</b>[<b class=\"text-success\">\"vsql_database\"</b>] = <b class=\"text-success\">\"dotravel4\";</b></p>
          <br>
          <p> Use it if you want better performance </p>

          <p><b class=\"text-danger\">\$_ENV</b>[<b class=\"text-success\">\"vsql_cache_dir\"</b>] = <b class=\"text-success\">\"/var/www/html/....\";</b></p>
        </div>
      </div>
      <div class=\"row\">
        <div class=\"col-sm-12\" >
          <h2>Query Maker Examples!</h2>

          <div class=\"text-muted\">
            <p> //initializes the sql proces </p>
          </div>

          <p> <b class=\"text-danger\">\$vas</b> = new <b class=\"text-warning\">VSQL</b>(<br><br>
            <i class=\"text-muted\"> /* save the query in the cache and also will store the connection to use it for multiple  */<br><br>  </i>
            <i class=\"text-success\">'name' </i>
            <i class=\"text-muted\"> <br><br> /* will debug the proces */<br><br>  </i>
            <i class=\"text-success\"> 'debug' </i><br><br>
          ); </p>
          <br>

          <p class=\"text-muted\">/* \$query = \$vas->query(\" //can be used to return the safe Query (Optional)*/</p>
          <p><b class=\"text-danger\">\$vas</b>-><i class=\"text-info\">query</i>(<i class=\"text-success\">\"</i></p>

          <pre><code class='sql'>
          SELECT * FROM items
          WHERE TRUE

            /* if  <b>'!'</b> into the tags and <b>:id</b> is null it will trow an exception if the field is empty */
            /*  <b>'!'</b> forces value to be used basically */
            AND  `id` = <&#33;:id>

           /* the section delimited by <b>{{ }}</b> will only appear if the <b>:status</b> is not empty*/
           {{ AND `status` = <:status> /* if <b>'!'</b> in tag will trow an exception if null */ }}

           /* if the section delimited by <b>{{ }}</b> has a some tag will only appear if the tag is set */
           /* tags should be delimited by ':' and appear at the start of the section and end with ':' like in the example */
           {{tag:id: AND `status` = 1 }}

            /* <b>@e or @E</b> //simbols will fetch a value from the \$_ENV Superglobal Variable */
            AND `model` = <@E:vsql_username>
            /* <b>@c or @C</b> //simbols will fetch a value from the \$_COOKIE Superglobal Variable */
            AND `time` = <@C:cookie_time>
            /* <b>@s or @S</b> //simbols will fetch a value from the \$_SESSION Superglobal Variable */
            AND  `supplier_id` = <@S!:supplier_id>
            /* <b>i or I</b> //simbols will cast the value to integer */
            AND  `customer` = <@i!:customer>
            /* <b>f or F</b> //simbols will cast the value to float */
            AND  `height` = <F!:height>
            /* <b>t or T</b> ///will trim the value */
            AND  location LIKE \"%< t!:location>%\"
            /* <b>s or S</b> ///add slashes to value */
            AND  program LIKE \"%< s:program>%\"
            /* <b>implode</b> //will implode the array */
            AND  FIND_IN_SET(name, \"< implode!:name>\")
            /* <b>json_get</b> ///will transorm the value to
            \"IF (JSON_VALID(content), JSON_UNQUOTE( JSON_EXTRACT(content, $.img)),NULL)\"
            */
            AND  content_type = < json_get:img,content> ;

            /* MULTIPLE QUERYES ARE ALLOWED */

            /*   VSQL OBJECTS     */
            SELECT
            cat.*

            , STD_VSQL(
                   'path' => path,
                   'name' => name,
                   'alt'  => alt
            ) AS media

            /*========== WILL BE TRANSFORMED INTO (name will not be changed and is requiered) =========*/

            , JSON_OBJECT(
                   'path' , path,
                   'name' , name,
                   'alt'  , alt
            ) AS media_original

            /*----------------------------------------------*/

            , ARRAY_VSQL(
                   'path' => path,
                   'name' => name,
                   'alt'  => alt
            ) AS media_array

            /*========== WILL BE TRANSFORMED INTO (name will not be changed and is requiered) =========*/

            , JSON_OBJECT(
                   'path' , path,
                   'name' , name,
                   'alt'  , alt
            ) AS media_array_original

            FROM images

          </code></pre>

         <i class=\"tab text-success\">\"</i> , <i class=\"text-info\">array</i>(

         <p> </p>


          <div class=\" t2 text-muted\">
            <p> /* here you set the values for the query */ </p>
          </div>

            <p class=\"t2\"> <i class=\"text-success\">\"program\"</i> => <i class=\"text-success\">\"default\"</i>, </p>
            <p class=\"t2\"> <i class=\"text-success\">\"location\"</i> => <i class=\"text-success\">\"us\"</i>, </p>
            <p class=\"t2\"> <i class=\"text-success\">\"img\"</i> => <i class=\"text-success\">\"/user/default.jpg\"</i>, </p>
            <p class=\"t2\"> <i class=\"text-success\">\"name\"</i> => <i class=\"text-success\">\"Wondeful icon\"</i>, </p>
            <p class=\"t2\"> <i class=\"text-success\">\"height\"</i> => <i class=\"text-success\">\"1.454\"</i>, </p>
            <p class=\"\"> )); </p>
          <br>

          <div class=\" text-muted\">
            <p> /* this will fetch the values in 1 row and return ans stdClass */ </p>
          </div>
          <p> <i class=\"text-danger\">\$vas</i>-><i class=\"text-info\">get</i>();</p>
          <br>

          <div class=\" text-muted\">
            <p> /* this will return a list of all rows*/ </p>
          </div>
          <p> <i class=\"text-danger\">\$vas</i>-><i class=\"text-info\">get</i>(<i class=\"text-warning\">true</i>);</p>

          <br>

          <div class=\" text-muted\">
            <p> /* this will execute the query and return the \$msqly object */ </p>
          </div>
          <p> <i class=\"text-danger\">\$vas</i>-><i class=\"text-info\">run</i>();</p>

        </div>
      </div>

      <br>
    </div>

    </body>
    <script>
    function copyToClipboard(element) {
        var \$temp = $(\"<input>\");
        $(\"body\").append(\$temp);
        \$temp.val($(element).text()).select();
        document.execCommand(\"copy\");
        \$temp.remove();
      }
    </script>
      </html>

    ";
  }

// ------------------------------------------------ <  _duplex > -------------------------------------------------------
  private function _duplex($from, $needle , $addslashes = true) {
      $array = $this->_tvar($from);

      if (is_int($needle) || ctype_digit((string)$needle)) {
          foreach ($array as $keyStr => $valueInt) {
              if ($needle == $valueInt) {
                  return $addslashes ? "'$keyStr'" : $keyStr;
              }
          }
      }else {
        return $array[$needle] === null ? null : "".$array[$needle];
      }

      return null;
  }

//------------------------------------------------ <  makemodel > ------------------------------------------------------
  private function _mkfunction( $table, $fun ){

    $this->query("
      SHOW COLUMNS FROM <!:tb> FROM <@E!:vsql_database>
    ", array('tb' => $table) );

    $vals = $this->get(true);

    switch ($fun) {
      case 'select':
        $this->_sel($vals ,$table);
        break;
    }

  }

//------------------------------------------------ <  makemodel > ------------------------------------------------------
  private function _sel($vals, $table){
    $sW = [];
    $sl = [];

    foreach ($vals as $key => $value) {
      $rp = str_repeat(" ",20 - strlen($value->Field));
      $sl[] = "\n\t`$value->Field`";
      $sW[] = "\n\t{{ AND `$value->Field` $rp = <:$value->Field> $rp}}";
    }

    $this->_error_msg("<strong>SELECT</strong><br><code class='php'>" .
    htmlentities("
    public static function get( array \$arr, \$list = false)  {
      \$vsql = new VSQL();

      \$vsql->query(\"SELECT " . implode($sl,',') . "
      FROM $table WHERE TRUE" . implode($sW,'') . "
      {{ ORDER BY <:order_by> }} {{ LIMIT <i:limit> {{, <i:limit_end> }} }} {{ OFFSET <i:offset> }}
    \");
      return \$vsql->get(\$list);
    }
    ")."

    </code>");
  }

//------------------------------------------------ <  _cache > ---------------------------------------------------------
  private function _cache(){
    $query_string = $this->query_string;

    if (!file_exists($_ENV['vsql_cache_dir'])) {
      mkdir($_ENV['vsql_cache_dir'], 0755, true);
    }

    if(empty($_ENV['vsql_cache_dir'])){
      $this->_error_msg("The cache directory is not set : use \$_ENV['vsql_cache_dir'] = '/var/www/html/vsql_cache'; to declare it!");
    }

    $filename = 'def';
    $check_data = 0;

    $e = new \Exception();
    foreach ($e->getTrace() as $key => $value) {
      if($value['function'] == 'query'){
        $bodytag = str_replace(DIRECTORY_SEPARATOR, "",$value['file']);
        $check_data = filemtime($value['file']);
        $filename = $_ENV['vsql_cache_dir'] . DIRECTORY_SEPARATOR . $bodytag . '.json';
        break;
      }
    }

    if (!file_exists($filename)) {
      /* if file cache don't exists we make a new one */
      return $this->_save_json_cache($query_string, array(), $check_data, $filename);
    } else {
      /* if file exists we get the content */
      $data = json_decode(utf8_decode(file_get_contents($filename)),true);

      if(!isset($data[$this->id])){
        /* if the id is not set in the file we add it */
        return $this->_save_json_cache($query_string, $data, $check_data, $filename);
      }else {

        /* if the id is set and the file has not been updated we return the query */
        if ($data[$this->id]['last_cache_update'] == $check_data) {
          $this->_transformed = isset($data[$this->id]['transformed']) ? $data[$this->id]['transformed'] : [];
          return $data[$this->id]['sql'];
        }

        /* we update the query */
        return $this->_save_json_cache($query_string, $data, $check_data, $filename);
      }
    }

    return "";
  }

//------------------------------------------- <  _save_json_cache > ----------------------------------------------------
  private function _save_json_cache($query_string, $data, $date, $filename) {

    $chekd = $this->_quote_check(
              $this->_find_objects($query_string)
    , true);

    $data[$this->id] = array(
        'last_cache_update' => $date,
        'transformed' => $this->_transformed,
        'sql'  => $chekd
    );

    $myfile = fopen($filename, "w");
    fwrite($myfile, json_encode($data));
    fclose($myfile);

    return $data[$this->id]['sql'];
  }

}

// $_ENV["vsql_servername"] = '172.17.0.2';
// $_ENV[ "vsql_username" ] = 'root';
// $_ENV[ "vsql_password" ] = 'dotravel';
// $_ENV[ "vsql_database" ] = 'dotravel';
// $_ENV["vsql_cache_dir" ] = __DIR__;
//
// $vsql = new VSQL('test');
// $vsql->query("SELECT
//
// , JGET_VSQL( content => type ) as s
//
// FROM items
//
// ",[],'debug');
