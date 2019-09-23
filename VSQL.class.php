<?php


namespace VSQL;
// $_ENV["vsql_servername"] = "127.0.0.1";
// $_ENV["vsql_username"] = "root";
// $_ENV["vsql_password"] = "password";
// $_ENV["vsql_database"] = "dotravel4";

class VSQL {

  private $CONN = null;

  // this came from http://php.net/manual/en/mysqli-result.fetch-field-direct.php
  private $mysql_data_type_hash = array(
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
      //252 is currently mapped to all text and blob types (MySQL 5.0.51a)
      253 =>array('varchar','string'),
      254 =>array('char','string'),
      246 =>array('decimal','float')
  );

  private $modifiers = array();
  private $query_vars = array();
  private $query_string = "";

//------------------------------------------------ <  __construct > ----------------------------------------------------
  function __construct($display = false) {

      foreach (array('servername','username','password','database') as $value) {
        if (empty($_ENV["vsql_".$value])) {
          $this->_error_msg("Enviroment value < \$_ENV['vsql_".$value."'] > is not set!");
        }
      }

      if($display){
        $this->_show_example();
      }

      $this->CONN = mysqli_connect(
          $_ENV["vsql_servername"],
          $_ENV["vsql_username"],
          $_ENV["vsql_password"],
          $_ENV["vsql_database"]
      );

      if ($this->CONN->connect_errno) {
        $this->_error_msg("Falló la conexión a MySQL: (" . $this->CONN->connect_errno . ") " . $this->CONN->connect_error);
      }

  }

//------------------------------------------------ <  add_global_vars > ------------------------------------------------
  private function _error_msg($error_msg) {
    echo "<div style='
          margin: auto;
          width: 50%;
          padding: 10px;
          font-family: Arial, Helvetica, sans-serif;
        '>";

    $this->_show_example();

    throw new \Exception('VSQL Error: ' . $error_msg."</div>", 1);
  }

//------------------------------------------------ <  global_scope > ------------------------------------------------
  function tags(array $params){
		$this->modifiers = array_merge($this->modifiers, $params );
	}


//------------------------------------------------ <  query > ----------------------------------------------------------
  function query(string $query_string, array $query_vars) : string {
    $this->query_vars = $query_vars;

    $query_string = $this->_quote_check($query_string);
    $query_string = $this->_var_transform($query_string);
    $this->query_string = $query_string;

    return $query_string;
  }

//------------------------------------------------ <  _var_transform > -------------------------------------------------
  private function _var_transform(string $query_string, $return_empty_if_has_null_values = false) : string {
    preg_match_all('!<(.*?)?(\!)?:(.*?)>!', $query_string, $match );

    foreach ($match[1] as $key => $simbol) {
      $var_key = $match[3][$key];
      $not_null = $match[2][$key];

      $var = $this->_convert_var($simbol, $var_key);

      if (empty($var)) {
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

//------------------------------------------------ <  _quote_check > ---------------------------------------------------
  private function _quote_check(string $query_string) : string {
    preg_match_all("!{{([^]*?\X*?[^{{]*?)}}!", $query_string, $match_brakets);

    while (count($match_brakets[0]) != 0) {

      foreach ($match_brakets[1] as $key => $value) {
        $res = $this->_var_transform($value , true);
        $query_string = str_replace($match_brakets[0][$key], $res, $query_string);
      }

      preg_match_all("!{{([^]*?\X*?[^{{]*?)}}!", $query_string, $match_brakets);
    }

    return $query_string;
  }

//------------------------------------------------ <  _get_var_from_query_vars > ---------------------------------------
  private function _qvar(string $var) {
    return empty($this->query_vars[$var]) ? null : $this->query_vars[$var];
  }

//------------------------------------------------ <  _convert_var > ---------------------------------------------------
  private function _convert_var(string $type, string $var){

    $result = '';
    //---------------------- cases -----------------
		switch ($type) {
      // if is empty does nothing only paste the value
      case '':
        $result = $this->_ecape_qvar($var);
        break;

      // @ or @T = fetch value from tags
      case '@':
      case '@T':
      case '@t':
        $result = empty($this->modifiers[$var]) ? null: $this->modifiers[$var];
        $result = $this->_sql_escape($result);
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
        settype($x, 'integer');
        $result = $this->_sql_escape($x);
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
        $result = $this->_ecape_qvar(implode(',', $x));
        break;

      // <json_get:what,from>
      case 'json_get':
        $js = explode(',',$var);
        if(empty($this->_qvar($js[0]))){
          $result = null;
          break;
        }
        $from = $this->_sql_escape($js[1]);
        $val = $this->_ecape_qvar($js[1]);

        $result = "IF (JSON_VALID($from), JSON_UNQUOTE( JSON_EXTRACT($from, $.$val)),NULL)";
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
    //-------------------------------------------

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
  public function get($list = true) {
    $mysqli = $this->CONN;
    $obj = new \stdClass();

    $nr = 0;
    if (mysqli_multi_query($mysqli, $this->query_string)) {
        if ($list) {
          do {
            if ($result = mysqli_store_result($mysqli)) {

              while ($proceso = mysqli_fetch_assoc($result)) {
                  $obj->$nr = $this->_fetch_row($result, $proceso);
                  $nr ++;
              }
              mysqli_free_result($result);
            }
          } while (mysqli_next_result($mysqli));

        }else {
          $result = mysqli_store_result($mysqli);
          $proceso = mysqli_fetch_assoc($result);
          $obj = $this->_fetch_row($result, $proceso);
        };

    }else {
      $this->_error_msg("Fail on query get: ");
    }

    return $obj;
  }

// ------------------------------------------------ <  run > -----------------------------------------------------------
  public function run() {
    $mysqli = $this->CONN;

    if (!$mysqli->multi_query($this->query_string)) {
      $this->_error_msg("Fail on query run: (" . $mysqli->errno . ") " . $mysqli->error);
    }

    return $mysqli;
  }

// ------------------------------------------------ <  _fetch_row > ----------------------------------------------------
  private function _fetch_row($result, $proceso) : \stdClass {
    $row = new \stdClass();

    $count = 0;
    foreach ($proceso as $key => $value) {
      $datatype = $result->fetch_field_direct($count)->type;
      $dt_str   = $this->mysql_data_type_hash[$datatype][1];
      settype($value,$dt_str);
      $row->$key = $value;
      $count++;
    }

    return $row;
  }

  public function _show_example() {
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
    </head>
    <style>

    .tab {margin-left: 30px;}
    .t2 {margin-left: 60px;}
    .t3 {margin-left: 90px;}

    </style>
    <body>

    <div class=\"jumbotron text-center\">
      <h1>VSQL INFO</h1>
    </div>

    <div class=\"container\" style=\"line-height: 70%;\">
      <div class=\"row\">
        <div class=\"col-sm-12\">
          <h2>INITIALIZE</h2>
          <p>Set on the SUPERGLOBAL <strong>\$_ENV</strong> your db values: (example) </p>
          <p><b class=\"text-danger\">\$_ENV</b>[<b class=\"text-success\">\"vsql_servername\"</b>] = <b class=\"text-success\">\"127.0.0.1\";</b></p>
          <p><b class=\"text-danger\">\$_ENV</b>[<b class=\"text-success\">\"vsql_username\"</b>] = <b class=\"text-success\">\"root\";</b></p>
          <p><b class=\"text-danger\">\$_ENV</b>[<b class=\"text-success\">\"vsql_password\"</b>] = <b class=\"text-success\">\"password\";</b></p>
          <p><b class=\"text-danger\">\$_ENV</b>[<b class=\"text-success\">\"vsql_database\"</b>] = <b class=\"text-success\">\"dotravel4\";</b></p>
        </div>
      </div>
      <div class=\"row\">
        <div class=\"col-sm-12\" >
          <h2>Query Maker Examples!</h2>

          <div class=\"text-muted\">
            <p> //starter </p>
            <p> //initializes the sql proces </p>
          </div>

          <p> <b class=\"text-danger\">\$vas</b> = new <b class=\"text-warning\">VSQL</b>(
            <i class=\"text-info\">true </i>
            <i class=\"text-muted\"> /* displays the vsql info (boolean) */  </i>
          ); </p>
          <br>

          <div class=\"text-muted\">
            <p> /* This field is optional */ </p>
          </div>
          <p> <b class=\"text-danger\">\$vas->tags</b>(<i class=\"text-info\">array</i>(</p>
          <div class=\" tab text-muted\">
            <p> /* here you initialize the global values that on the query will be replaced with the desired values*/ </p>
          </div>
            <p class=\"tab\"> <i class=\"text-success\">\"lang\"</i> => <i class=\"text-success\">\"English\"</i>, </p>
            <p class=\"tab\"> <i class=\"text-success\">\"user\"</i> => <i class=\"text-success\">\"Mateo\"</i>, </p>
            <p class=\"tab\"> <i class=\"text-success\">\"tester\"</i> => <i class=\"text-success\">\"Vasyl\"</i>, </p>
            <p class=\"tab\"> <i class=\"text-success\">\"model\"</i> => <i class=\"text-success\">\"icon\"</i>, </p>

          <p> )); </p>
          <br>

          <p class=\"text-muted\">/* \$query = \$vas->query(\" //can be used to return the safe Query (Optional)*/</p>
          <p><b class=\"text-danger\">\$vas</b>-><i class=\"text-info\">query</i>(<i class=\"text-success\">\"</i></p>
          <p class=\"tab\">
            <i class=\"text-info\">SELECT</i> *
          </p>


         <p class=\"tab\">
           <i class=\"text-info\">FROM</i> items
         </p>

         <p class=\"tab\">
           <i class=\"text-info\">WHERE</i> <i class=\"text-warning\">TRUE</i>
         </p>
         <br>

         <p class=\"t2 text-muted\">/* if  <b>'!'</b> into the tags and <b>:id</b> is null it will trow an exception */</p>
         <p class=\"t2 text-muted\">/*  <b>'!'</b> forces value to be used */</p>
         <p class=\"t2\"> <i class=\"text-danger\">AND</i> id = <&#33;:id> </p>
         <br>
         <p class=\"t2 text-muted\">/* the section delimited by <b>{{ }}</b> will only appear if the <b>:status</b> is not empty*/</p>
         <p class=\"t2\"> {{ <i class=\"text-danger\">AND</i> status = <:status> }} </p>
         <br>
         <p class=\"t2 text-muted\">/* can be used also with <b>'!'</b> */</p>
         <p class=\"t2\"> {{ <i class=\"text-danger\">AND</i> type = <&#33;:type> }} </p>
         <br>
         <p class=\"t2 text-muted\">/* <b>@ or @T or @t</b> //simbols will fetch a value from the inserted tags */</p>
         <p class=\"t2\"> {{ <i class=\"text-danger\">AND</i> model = <@:model> }} </p>
         <br>
         <p class=\"t2 text-muted\">/* <b>@e or @E</b> //simbols will fetch a value from the \$_ENV Superglobal Variable */</p>
         <p class=\"t2\"> {{ <i class=\"text-danger\">AND</i> user = <@e:vsql_username> }} </p>
         <br>
         <p class=\"t2 text-muted\">/* <b>@c or @C</b> //simbols will fetch a value from the \$_COOKIE Superglobal Variable */</p>
         <p class=\"t2\"> {{ <i class=\"text-danger\">AND</i> time = <@c!:cookie_time> }} </p>
         <br>
         <p class=\"t2 text-muted\">/* <b>@s or @S</b> //simbols will fetch a value from the \$_SESSION Superglobal Variable */</p>
         <p class=\"t2\"> <i class=\"text-danger\">AND</i> supplier_id = <@S!:supplier_id> </p>
         <br>
         <p class=\"t2 text-muted\">/* <b>i or I</b> //simbols will cast the value to integer */</p>
         <p class=\"t2\"> <i class=\"text-danger\">AND</i> customer = < I!:customer> </p>
         <br>
         <p class=\"t2 text-muted\">/* <b>f or F</b> //simbols will cast the value to float */</p>
         <p class=\"t2\"> <i class=\"text-danger\">AND</i> height = < F!:height> </p>
         <br>
         <p class=\"t2 text-muted\">/* <b>implode</b> //will implode the array */</p>
         <p class=\"t2\"> <i class=\"text-danger\">AND</i> FIND_IN_SET(name, \"< implode!:name>\") </p>
         <br>
         <p class=\"t2 text-muted\">/* <b>json_get</b> //will transorm the value to  </p>
         <p class=\"t2 text-muted\">\"IF (JSON_VALID(content), JSON_UNQUOTE( JSON_EXTRACT(content, $.img)),NULL)\" */</p>
         <p class=\"t2\"> <i class=\"text-danger\">AND</i> content_type = < json_get:img,content> </p>
         <br>
         <p class=\"t2 text-muted\">/* <b>t or T</b> //will trim the value */</p>
         <p class=\"t2\"> <i class=\"text-danger\">AND</i> location <i class=\"text-info\">LIKE</i> \"%< t!:location>%\" </p>
         <br>
         <p class=\"t2 text-muted\">/* <b>s or S</b> //will add slashes to value*/</p>
         <p class=\"t2\"> <i class=\"text-danger\">AND</i> program <i class=\"text-info\">LIKE</i> < s:program> </p>

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
      <br>
      <br>
      <br>
      <br>
      <br>
      <br>
      <br>
      <br>
    </div>

    </body>
    </html>

    ";
  }

}
?>
