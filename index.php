<?php

$_ENV["vsql_servername"] = "127.0.0.1";
$_ENV["vsql_username"] = "root";
$_ENV["vsql_password"] = "password";
$_ENV["vsql_database"] = "dotravel4";

class VSQL {

  private $CONN = null;

  // this came from http://php.net/manual/en/mysqli-result.fetch-field-direct.php
  private $mysql_data_type_hash = array(
      1=>'tinyint',
      2=>'smallint',
      3=>'int',
      4=>'float',
      5=>'double',
      7=>'timestamp',
      8=>'bigint',
      9=>'mediumint',
      10=>'date',
      11=>'time',
      12=>'datetime',
      13=>'year',
      16=>'bit',
      //252 is currently mapped to all text and blob types (MySQL 5.0.51a)
      253=>'varchar',
      254=>'char',
      246=>'decimal'
  );

  private $modifiers = array();
  private $query_vars = array();

//------------------------------------------------ <  __construct > ----------------------------------------------------
  function __construct() {

      foreach (array('servername','username','password','database') as $value) {
        if (empty($_ENV["vsql_".$value])) {
          $this->_error_msg("Enviroment value < \$_ENV['vsql_".$value."'] > is not set!");
        }
      }

      $this->CONN = mysqli_connect(
          $_ENV["vsql_servername"],
          $_ENV["vsql_username"],
          $_ENV["vsql_password"],
          $_ENV["vsql_database"]
      );

  }

//------------------------------------------------ <  add_global_vars > ------------------------------------------------
  private function _error_msg($error_msg) {
    echo "<div style='
          margin: auto;
          width: 50%;
          padding: 10px;
          font-family: Arial, Helvetica, sans-serif;
        '>";

    trigger_error('VSQL Error: ' . $error_msg);

    echo "</div>";
    die();
  }

//------------------------------------------------ <  global_scope > ------------------------------------------------
  function tags(array $params){
		$this->modifiers = array_merge($this->modifiers, $params );
	}


//------------------------------------------------ <  query > ----------------------------------------------------------
  function query(string $query_string, array $query_vars) : string {
    $this->query_vars = $query_vars;

    echo "<br><textarea>";
    print_r( $query_string);
    echo "</textarea>";

    echo "<br><textarea>";
    $this->_quote_check($query_string);
    echo "</textarea>";

    // $query_string = $this->_var_transform($query_string);


    // echo "<br><textarea>";
    // print_r($match_brakets[1]);
    // echo "</textarea>";
    //
    echo "<br><textarea>";
    print_r( $query_string);
    echo "</textarea>";

    return $query_string;
  }

//------------------------------------------------ <  _var_transform > -----------------------------------------------
  private function _var_transform(string $query_string) : string {
    preg_match_all('!<(.*?)?(\!)?(\?+)?:(.*?)>!', $query_string, $match );

    foreach ($match[1] as $key => $simbol) {
      $var_key = $match[4][$key];
      $not_null = $match[2][$key];
      $in_brackets = $match[3][$key];


      $var = $this->_convert_var($simbol, $var_key);

      if (empty($var) && $not_null == "!") {
        $this->_error_msg("$var_key key resulted in null!");
      }

      if (empty($in_brackets)) {
        $query_string = str_replace( $match[0][$key], $var, $query_string );
        continue;
      }

    }

    return $query_string;
  }

//------------------------------------------------ <  _quote_check > -----------------------------------------------
  private function _quote_check(string $query_string) : string {
    $ds = "{{";
    $df = "}}";
    preg_match_all("!$ds([^]*?\X*?[^$ds]*?)$df!", $query_string, $match_brakets);

    // while ($a <= 10) {
    //   // code...
    // }
    foreach ($match_brakets[1] as $key => $value) {
      $res = $this->_var_transform($value,$this->query_vars);
      echo "---$res";
    }
  }

//------------------------------------------------ <  _quote_check > -----------------------------------------------
  private function _quote_check(string $query_string) : string {
    $ds = "{{";
    $df = "}}";
    preg_match_all("!$ds(\X*?[^$ds]*)$df!", $query_string, $match_brakets);

    foreach ($match_brakets[1] as $key => $value) {
      $res = $this->_var_transform($value,$this->query_vars);
      echo "---$res";
    }
  }

//------------------------------------------------ <  _get_var_from_query_vars > -----------------------------------------------
  private function _qvar(string $var) {
    return empty($this->query_vars[$var]) ? null : $this->query_vars[$var];
  }

//------------------------------------------------ <  _convert_var > -----------------------------------------------
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
        $result = empty($this->modifiers[$var]) ? null: $this->modifiers[$var];
        $result = $this->_sql_escape($result);
        break;

      // @E = fetch value from $ENV
      case '@E':
        $result = empty($_ENV[$var]) ? null: $_ENV[$var];
        $result = $this->_sql_escape($result);
        break;

      // @E = fetch value from $_COOKIE
      case '@C':
        $result = empty($_COOKIE[$var]) ? null: $_COOKIE[$var];
        $result = $this->_sql_escape($result);
        break;

      // @E = fetch value from $_SESSION
      case '@S':
        $result = empty($_SESSION[$var]) ? null: $_SESSION[$var];
        $result = $this->_sql_escape($result);
        break;

      // cast to integer
      case 'I':
        $x = $this->_qvar($var);
        settype($x, 'integer');
        $result = $this->_sql_escape($x);
        break;

      // cast to float
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
      case 'T':
        $result = $this->_sql_escape(trim($this->_qvar($var)));
        break;

    }
    //-------------------------------------------

    return $result;
	}

//------------------------------------------------ <  _sql_escape > -----------------------------------------------
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

//------------------------------------------------ <  _ecape_qvar > -----------------------------------------------
  private function _ecape_qvar(string $var) {
    return $this->_sql_escape($this->_qvar($var));
  }


}

$vas = new VSQL();
$vas->tags(array("lang" => "English"));

$vas->query("
<json_get:content,content>
<@E:vsql_servername>
<:vasyl>

{{

  {{ <T?:vasyl>  }}
   <:vasylena>


}}
",array(
  "vasyl" => "genius",
  "content" => "aaaa",
// "vasylena" => "genius2",

));



//
// $servername = "127.0.0.1";
// $username = "root";
// $password = "password";
// $db = "dotravel4";
// $mysqli = mysqli_connect($servername, $username, $password, $db);
//
// $mysql_data_type_hash = array(
//     1=>'tinyint',
//     2=>'smallint',
//     3=>'int',
//     4=>'float',
//     5=>'double',
//     7=>'timestamp',
//     8=>'bigint',
//     9=>'mediumint',
//     10=>'date',
//     11=>'time',
//     12=>'datetime',
//     13=>'year',
//     16=>'bit',
//     //252 is currently mapped to all text and blob types (MySQL 5.0.51a)
//     253=>'varchar',
//     254=>'char',
//     246=>'decimal'
// );
//
// // run the query...
// $result = $mysqli->query("select * from products");
//
// // get one row of data from the query results
// $proceso = mysqli_fetch_assoc($result);
//
// print "<table>
//         <tr>
//            <th>\$key</th>
//            <th>\$value</th>
//            <th>\$datatype</th>
//            <th>\$dt_str</th>
//         </tr>  ";
//
// // to count columns for fetch_field_direct()
// $count = 0;
//
// // foreach column in that row...
// foreach ($proceso as $key => $value) {
//   $datatype = $result->fetch_field_direct($count)->type;
//   $dt_str   = $mysql_data_type_hash[$datatype];
//   $value    = (empty($value)) ? 'null' : $value;
//
//   print "<tr>
//            <td>$key</td>
//            <td>$value</td>
//            <td class='right'>$datatype</td>
//            <td>$dt_str</td>
//          </tr>  ";
//   $count++;
// }
//
// print "</table>";
//
// mysqli_close($mysqli);
?>
<!--
<style>
   /* this is css that you don't need but i was bored so i made it pretty...! */
   table   { font-family:Courier New;
             border-color:#E5E8E3; border-style:solid; border-weight:1px; border-collapse:collapse;}
   td,th   { padding-left:5px; padding-right:5px; margin-right:20px;
             border-color:#E5E8E3; border-style:solid; border-weight:1px; }
   .right  { text-align:right }
</style> -->
