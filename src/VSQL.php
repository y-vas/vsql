<?php
namespace VSQL\VSQL;

//
//                                           ██╗     ██╗ ███████╗  ██████╗  ██╗
//                                           ██║    ██║ ██╔════╝ ██╔═══██╗ ██║
//                                           ██║   ██║ ███████╗ ██║   ██║ ██║
//                                          ╚██╗  ██║ ╚════██║ ██║▄▄ ██║ ██║
//                                           ╚████╔╝  ███████║╚ ██████║ ███████╗
//                                             ╚═══╝   ╚══════╝ ╚══▀▀═╝ ╚══════╝
//

use Exception;

class ExVSQL extends Exception {}

class VSQL {
    public $CONN = null;
    private $query_vars = array();
    private $query_string = "";
    private $query_original = "";
    private $throws_exception = "default";
    private $concat_name = false;
    private $is_transaction = false;
    private $_transformed = array();
    public $id = '';

//------------------------------------------------ <  _construct > -----------------------------------------------------
    function __construct($id = 0, string $exception = "default") {
        $this->id = $id;
        $this->throws_exception = $exception;


        foreach (array('host', 'user', 'pass', 'db') as $value) {
            if (!isset($_ENV["sql_" . $value])) {
                $this->_error_msg("Enviroment value < \$_ENV['sql_" . $value . "'] > is not set!");
            }
        }

        if (!empty($_ENV["SQL_CONN{$id}"])) {
            $this->CONN = $_ENV["SQL_CONN{$id}"];
        } else {
            $this->CONN = self::_conn();
        }

        if ($this->CONN->connect_errno) {
            $this->_error_msg("Connection Fail: (" .
                $this->CONN->connect_errno
                . ") " . $this->CONN->connect_error
            );
        }

        $_ENV["SQL_CONN{$id}"] = $this->CONN;
    }

//------------------------------------------------ <  _conn > ----------------------------------------------------------
    private function _conn() {
        return mysqli_connect(
            $_ENV['sql_host'],
            $_ENV['sql_user'],
            $_ENV['sql_pass'],
            $_ENV['sql_db']
        );
    }

//------------------------------------------------ <  TRANSACTION > ----------------------------------------------------
//   public function start_transaction() {
//     $this->is_transaction = true;
//     $this->CONN->autocommit(FALSE);
//
//     $this->CONN->begin_transaction(
//         MYSQLI_TRANS_START_READ_WRITE
//     );
//   }
//
// //------------------------------------------------ <  TRANSACTION > ----------------------------------------------------
//   public function end_transaction() {
//     $this->CONN->commit();
//   }
//
//   public function rollback_transaction() {
//     $this->CONN->rollback();
//   }
//------------------------------------------------ <  _error_msg > -----------------------------------------------------
    public function _error_msg( $error_msg ) {

        if ($this->throws_exception == 'pretty') {
            $content = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'info.html');

            $values = array(
                "error_messages"    => "<div>" . $error_msg . "</div>",
                "original_query"    => htmlentities($this->query_original),
                "transformed_query" => htmlentities($this->query_string),
            );

            foreach ($values as $key => $value) {
                $content = str_replace("<$key>", $value, $content);
            }

            echo $content;
            die;
        }

        if ($this->throws_exception == 'default') {
            throw new Exception("Error : " . $error_msg);
        }

        throw new ExVSQL("Error : " . $error_msg);
    }

//------------------------------------------------ <  query > ----------------------------------------------------------
    public function query(string $query_string, array $query_vars, $debug = ""): string {

        $this->query_original = $query_string;
        $this->query_vars = $query_vars;
        $this->query_string = $query_string;

        if(!$this->_assoc($query_vars)){
          // SAFE SQL SYNTAX
          $query_string = $this->_safe_sql_query($query_string,$query_vars);

        }else {
          // new SYNTAX
          $cache = "";
          if (!empty($this->id)) {
            $cache = $this->_cache();
          }

          if (empty($cache)) {
            $query_string = $this->_find_objects($query_string);
            $query_string = $this->_quote_check($query_string);
            $query_string = $this->_var_transform($query_string);
          } else {
            $query_string = $this->_var_transform($cache);
          }

        }
        $this->query_string = $query_string;

        $this->_inspect($debug);
        return $query_string;
    }

//--------------------------------------- <  safe_sql_query > ----------------------------------------------------------
    private function _safe_sql_query($query_string, $query_vars) {
      $_var_count = count($query_vars);

      if($_var_count != preg_match_all('!%[sSiIfFcClLqQnN]!', $query_string, $_match)) {
        $this->_error_msg('Unmatched number of vars and % placeholders: ');
      }

      $_var_pos = array();
      $_curr_pos = 0;

      for( $_x = 0; $_x < $_var_count; $_x++ ) {
        $_var_pos[$_x] = strpos($query_string, $_match[0][$_x], $_curr_pos);
        $_curr_pos = $_var_pos[$_x] + 1;
      }

      $_last_removed_pos = null;
      $_last_var_pos = null;

      for( $_x = $_var_count-1; $_x >= 0; $_x-- ) {

        if( isset($_last_removed_pos) && $_last_removed_pos < $_var_pos[$_x] ) {
            continue;
        }

        // escape string
        $query_vars[$_x] = $this->_sql_escape($query_vars[$_x]);


        if(in_array($_match[0][$_x], array('%S','%I','%F','%C','%L','%Q','%N'))) {

          // get positions of [ and ]
          $_right_pos = strpos($query_string, ']', isset($_last_var_pos) ? $_last_var_pos : $_var_pos[$_x]);

          // no way to get strpos from the right side starting in the middle
          // of the string, so slice the first part out then find it

          $_str_slice = substr($query_string, 0, $_var_pos[$_x]);
          $_left_pos = strrpos($_str_slice, '[');

          if($_right_pos === false || $_left_pos === false) {
            $this->_error_msg('Missing or unmatched brackets: ');
          }
          if(in_array($query_vars[$_x], $this->_drop_values, true)) {
            $_last_removed_pos = $_left_pos;
            // remove entire part of string
            $query_string = substr_replace($query_string, '', $_left_pos, $_right_pos - $_left_pos + 1);
            $_last_var_pos = null;
            } else if ($_x > 0 && $_var_pos[$_x-1] > $_left_pos) {
                // still variables left in brackets, leave them and just replace var
                $_convert_var = $this->_convert_var($_match[0][$_x],$query_vars[$_x]);
                $query_string = substr_replace($query_string, $_convert_var, $_var_pos[$_x], 2);
                $_last_var_pos = $_var_pos[$_x] + strlen($_convert_var);
            } else {
              // remove the brackets only, and replace %S
              $query_string = substr_replace($query_string, '', $_right_pos, 1);
              $query_string = substr_replace($query_string, $this->_convert_var( $_match[0][$_x],$query_vars[$_x]), $_var_pos[$_x], 2);
              $query_string = substr_replace($query_string, '', $_left_pos, 1);
              $_last_var_pos = null;
            }
        } else {
          $query_string = substr_replace($query_string, $this->_convert_var( $_match[0][$_x],$query_vars[$_x] ), $_var_pos[$_x], 2);
        }
      }


      return $query_string;
    }
//------------------------------------------------ <  _inspect > -------------------------------------------------------
    private function _inspect( $debug ) {
        $this->throws_exception = "pretty";

        $extra = '';
        if (strpos($debug, ':') !== false) {
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
                var_dump($this->get(isset($extra[1])));
                $result = ob_get_clean();
                $this->_error_msg("<strong>VAR DUMP</strong> : <br> <code class='scss'> $result </code>");
                break;

            case 'mk_funk':
                $this->_mkfunction($extra[1], $extra[2]);
                break;
        }
    }

//-------------------------------------------- <  _find_objects > ------------------------------------------------------
    private function _find_objects( $query_string ) {
        preg_match_all('!(\w*?)_VSQL\((\X*)!', $query_string, $match);

        $counter = 0;
        while (count($match[0]) != 0) {
            $counter++;

            if ($counter > 100) {
                $this->_error_msg("Query to large! OR some function is not set propertly!");
            }

            $ad = 1;
            $str = "";
            for ($l = 0; $l < strlen($match[2][0]); $l++) {
                $lt = $match[2][0][$l];
                if ($ad == 0) {
                    $str = substr($str, 0, -1);
                    break;
                }
                if ($lt == ")") {
                    $ad--;
                }
                if ($lt == "(") {
                    $ad++;
                }
                $str = $str . $lt;
            }

            preg_match_all('!(\w*?)_VSQL\(\Q' . $str . '\E\)(?:\s*?\s*(?:as|AS)\s*(\w*)\s*)?!', $query_string,
                $match);
            foreach ($match[2] as $key => $value) {
                $replace = $this->_vsql_function($match[1][$key], $str, $match[2][$key]);
                $query_string = str_replace($match[0][$key], $replace, $query_string);
            }

            preg_match_all("!(\w*?)_VSQL\((\X*)!", $query_string, $match);
        }

        return $query_string;
    }

//-------------------------------------------- <  _vsql_function > -----------------------------------------------------
    private function _vsql_function( $func, $vals, $name ) {
        $lname = "";

        if (!empty($name)) {
            $lname = " AS $name \n\n";
        }

        $vals = preg_replace('![^<](=>)!', ',', $vals);

        switch ($func) {
            case 'STD':
                $this->_transformed[$name] = ['json'];
                return 'JSON_OBJECT(' . $vals . ')' . $lname;
                break;

            case 'JGET':
                $this->_transformed[$name] = ['json'];
                $vales = explode(",", $vals);

                if (count($vales) != 2) {
                    $this->_error_msg('JGET_VSQL(' . $vals . ') : Requieres only 2 values !');
                }

                $v1 = trim($vales[0]);
                $v2 = trim($vales[1]);

                return "(SELECT IF( JSON_VALID($v1), JSON_UNQUOTE( JSON_EXTRACT($v1,'$.$v2') ), NULL) )" . $lname;
                break;

            case 'ARRAY':
                $this->_transformed[$name] = ['array'];
                return 'JSON_OBJECT('.$vals.')'.$lname;
                break;

            case 'JAGG':
                $this->_transformed[$name] = ['json'];
                $tr = trim($vals);

                $part = "";
                $fields = [];
                $ad = 0;
                for ($l = 0; $l < strlen($tr); $l++) {
                    $lt = $tr[$l];
                    $part .= $lt;
                    if ($lt == ")") { $ad--; }
                    if ($lt == "(") { $ad++; }
                    if ($lt == ',' && $ad == 0) {
                        $fields[] = trim(substr_replace($part, "", -1));
                        $part = '';
                    }
                }

                $fields[] = $part;
                $tr = "";

                foreach ($fields as $key => $value) {
                    if ($key % 2 == 0) {

                        if (!isset($fields[$key + 1])) {
                            $this->_error_msg("Error: unmatched values for JAGG_VSQL($vals)");
                        }

                        $k = trim(str_replace("'", "", str_replace("\"", "", $value)));
                        $f = $fields[$key + 1];

                        $tr .= "\nCONCAT('{\"$k\":',
                          IF(CONVERT($f, SIGNED INTEGER) IS NOT NULL,$f,concat('\"', $f ,'\"'))
              ,'}')\n,";

                    }
                }

                return '
                CONCAT(\'[\',GROUP_CONCAT(JSON_MERGE(
                  ' . $tr . '
                \'{}\',\'{}\') SEPARATOR \',\' ) ,\']\')
                ' . $lname . " \n\n";
                break;

            case 'TO_STD':
                $this->_transformed[$name] = ['json'];
                return '(' . $vals . ')' . $lname;
                break;

            case 'COLLECTION':
                $this->_transformed[$name] = ['json'];
                return "concat('[',group_concat(json_object(" . $vals . ")),']')" . $lname;
                break;

            case 'TO_ARRAY':
                $this->_transformed[$name] = ['array'];
                return '(' . $vals . ')' . $lname;
                break;
        }

        return "";
    }

//------------------------------------------- <  _var_transform > ------------------------------------------------------
    private function _var_transform(
        string $query_string,
        $return_empty_if_has_null_values = false
    ): string {
        preg_match_all('!<(.*?)?(\!)?:(.*?)>!', $query_string, $match);

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
    private function _quote_check(string $query_string, $cache = false ): string {
        preg_match_all("!{{([\w*?:\!]*)([^{{]*?)}}!", $query_string, $match_brakets);

        while (count($match_brakets[0]) != 0) {

            foreach ($match_brakets[2] as $key => $value) {

                $tags = explode(':', $match_brakets[1][$key]);
                if (count($tags) > 1) {
                    $show = false;

                    foreach ($tags as $h => $t) {
                        $tl = trim(str_replace('!', '', $t));
                        $negate = (trim($t) != $tl);

                        if ($negate && !isset($this->query_vars[$tl])) {
                            $show = true;
                            break;
                        }

                        if (isset($this->query_vars[$tl]) && $negate == false) {
                            $show = true;
                            break;
                        }

                    }

                    if ($show == false) {
                        $value = "";
                    }
                }

                $res = $this->_var_transform($value, true);
                if ($cache && !empty($res)) {
                    $res = $value;
                }

                $query_string = str_replace($match_brakets[0][$key], $res, $query_string);

            }

            preg_match_all("!{{([\w*?:\!]*)([^{{]*?)}}!", $query_string, $match_brakets);
        }


        return $query_string;
    }

//------------------------------------------------ <  _get_var_from_query_vars > ---------------------------------------
    private function _qvar( string $var ) {
        return empty($this->query_vars[$var]) ? null : $this->query_vars[$var];
    }

//------------------------------------------------ <  _get_var_from_query_vars > ---------------------------------------
    private function _tvar( string $var ) {
        return empty($this->tags[$var]) ? null : $this->tags[$var];
    }

//------------------------------------------------ <  _convert_var > ---------------------------------------------------
    private function _convert_var( string $type, string $var ) {

        $result = null;
        //---------------------- cases -----------------
        switch ($type) {
            // if is empty does nothing only paste the value
            case '':
                $result = $this->_escape_qvar($var);
                break;

            // @E = fetch value from $ENV
            case '@e':
            case '@E':
                $result = empty($_ENV[$var]) ? null : $_ENV[$var];
                $result = $this->_sql_escape($result);
                break;

            // @E = fetch value from $_COOKIE
            case '@c':
            case '@C':
                $result = empty($_COOKIE[$var]) ? null : $_COOKIE[$var];
                $result = $this->_sql_escape($result);
                break;

            // @E = fetch value from $_SESSION
            case '@s':
            case '@S':
                $result = empty($_SESSION[$var]) ? null : $_SESSION[$var];
                $result = $this->_sql_escape($result);
                break;

            // cast to integer
            case 'i':
            case 'I':
                $x = $this->_qvar($var);
                if ($x != null) {
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
                $result = $res != null ? "'" . $res . "'" : $res;
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
                $result = $res != null ? "'" . $res . "'" : $res;
                break;

            // ------------------------------------------- safe sql varypes

            case '%i':
      			case '%I':
      				// cast to integer
      				settype($var, 'integer');
              $result = $var;
      				break;
      			case '%f':
      			case '%F':
      				// cast to float
      				settype($var, 'float');
              $result = $var;

      				break;
      			case '%c':
      			case '%C':
      				// comma separate
      				settype($var, 'array');
      				for($_x = 0 , $_y = count($var); $_x < $_y; $_x++) {
      					// cast to integers
      					settype($var[$_x], 'integer');
      				}
      				$var = implode(',', $var);
      				if($var == '') {
      					// force 0, keep syntax from breaking
      					$var = '0';
      				}

              $result = $var;
      				break;
      			case '%l':
      			case '%L':
      				// comma separate
      				settype($var, 'array');
              $result = implode(',', $var);

      				break;
      			case '%q':
      			case '%Q':
      				settype($var, 'array');
      				// quote comma separate
      				$result = "'" . implode("','", $var) . "'";
      				break;
                  case '%n':
                  case '%N':
                      if($var != 'NULL')
                          $result = "'" . $var . "'";
                      break;

        }

        return $result;
    }

//------------------------------------------------ <  _sql_escape > ----------------------------------------------------
    private function _sql_escape( $var ) {
        if (is_array($var)) {
            foreach ($var as $_element) {
                $_newvar[] = $this->_sql_escape($_element);
            }
            return $_newvar;
        }

        if (function_exists('mysql_real_escape_string')) {
            if (!isset($this->CONN)) {
                return mysql_real_escape_string($var);
            } else {
                return mysql_real_escape_string($var, $this->CONN);
            }
        } elseif (function_exists('mysql_escape_string')) {
            return mysql_escape_string($var);
        } else {
            return addslashes($var);
        }
    }

//------------------------------------------------ <  _ecape_qvar > ----------------------------------------------------
    private function _escape_qvar( string $var ) {
        return $this->_sql_escape($this->_qvar($var));
    }

//------------------------------------------------ <  get > ------------------------------------------------------------
    public function get( $list = false, $type = "array" ) {
        $mysqli = $this->CONN;
        $obj = null;

        if ($type == "array") {
            $obj = array();
        } else {
            $obj = new \stdClass();
        }

        $nr = 0;
        if (mysqli_multi_query($mysqli, $this->query_string)) {
            if ($list) {
                do {
                    if ($result = mysqli_store_result($mysqli)) {

                        while ($proceso = mysqli_fetch_assoc($result)) {
                            $rt = $this->_fetch_row($result, $proceso);

                            if ($type == "array") {
                                $obj[$nr] = $rt;
                            } else {
                                $obj->$nr = $rt;
                            }

                            $nr++;
                        }

                        mysqli_free_result($result);
                    }

                    if (!mysqli_more_results($mysqli)) {
                        break;
                    }

                } while (mysqli_next_result($mysqli) && mysqli_more_results());

            } else {
                $result = mysqli_store_result($mysqli);
                $proceso = mysqli_fetch_assoc($result);
                if($proceso == null){
                  $obj = null;
                }else {
                  $obj = $this->_fetch_row($result, $proceso);
                }
            };

        } else {
            $this->_error_msg("Fail on query get :" . mysqli_error($mysqli));
        }
        return $obj;
    }

//------------------------------------------------ <  run > ------------------------------------------------------------
    public function run( $list = false ) {
        $mysqli = $this->CONN;

        $mysqli->query($this->query_string);

        // if (!empty($mysqli->error)){
        //   $msg = $mysqli->error;
        //   if ($this->is_transaction){
        //     $mysqli->rollback();
        //   }
        //   $this->_error_msg($msg);
        // }

        return $mysqli;
    }

// ------------------------------------------------ <  _fetch_row > ----------------------------------------------------
    private function _fetch_row( $result, $proceso ): \stdClass {
        $row = new \stdClass();

        $count = 0;
        foreach ($proceso as $key => $value) {
            $direct = $result->fetch_field_direct($count);
            $ret = $this->_transform_get($value, $direct->type, $key);
            $key = $ret[1];

            if ($this->concat_name == true) {
                $key = $direct->orgtable . "__" . $key;
            }

            $row->$key = $ret[0];
            $count++;
        }

        return $row;
    }

// ------------------------------------------------ <  _transform_get > ------------------------------------------------
    public function _transform_get( $val, string $datatype, string $key ) {
        $mysql_data_type_hash = array(
            1 => array('tinyint', 'int'),
            2 => array('smallint', 'int'),
            3 => array('int', 'int'),
            4 => array('float', 'float'),
            5 => array('double', 'double'),
            7 => array('timestamp', 'string'),
            8 => array('bigint', 'int'),
            9 => array('mediumint', 'int'),
            10 => array('date', 'string'),
            11 => array('time', 'string'),
            12 => array('datetime', 'string'),
            13 => array('year', 'int'),
            16 => array('bit', 'int'),
            253 => array('varchar', 'string'),
            254 => array('char', 'string'),
            246 => array('decimal', 'float')
        );


        $dt_str = "string";
        if (isset($mysql_data_type_hash[$datatype][1])) {
            $dt_str = $mysql_data_type_hash[$datatype][1];
        }


        settype($val, $dt_str);
        if ($dt_str) {
            $val = utf8_encode($val);
        }

        foreach ($this->_transformed as $k => $value) {
            if (trim($key) == trim($k)) {
                foreach ($value as $t => $tr) {
                    $val = $this->_transform($tr, $val);

                }

            }
        }

        return array($val, $key);
    }

// ------------------------------------------------ <  _transform > ----------------------------------------------------
    private function _transform( $transform, $val ) {
        switch ($transform) {
          case 'json':
              $non = json_decode($val,true);
              if ($non!=null){
                return (object) $non;
              }
              return (object)json_decode(utf8_decode($val), true);
          case 'array':
              $non = json_decode($val,true);
              if ($non!=null){
                return $non;
              }
              return json_decode(utf8_decode($val), true);
        }
        return $val;
    }

//------------------------------------------------ <  makemodel > ------------------------------------------------------
    private function _mkfunction(
        $table,
        $fun
    ) {

        $this->query("SHOW COLUMNS FROM <!:tb> FROM <@E!:vsql_database> ", array('tb' => $table));

        $vals = $this->get(true);

        switch ($fun) {
            case 'select':
                $this->_sel($vals, $table);
                break;
        }

    }

//------------------------------------------------ <  makemodel > ------------------------------------------------------
    private function _sel(
        $vals,
        $table ) {
        $sW = [];
        $sl = [];

        foreach ($vals as $key => $value) {
            $rp = str_repeat(" ", 20 - strlen($value->Field));
            $sl[] = "\n\t`$value->Field`";
            $sW[] = "\n\t{{ AND `$value->Field` $rp = <:$value->Field>$rp }}";
        }

        $this->_error_msg("<strong>SELECT</strong><br><code class='php'>" . htmlentities("
    public static function get( array \$arr, \$list = false, \$stored = '')  {
      \$vsql = new VSQL(\$stored);

      \$vsql->query(\"SELECT " . implode($sl, ',') . "
      FROM $table WHERE TRUE" . implode($sW, '') . "
      {{ ORDER BY <:order_by> }} {{ LIMIT <i:limit> {{, <i:limit_end> }} }} {{ OFFSET <i:offset> }}
    \");
      return \$vsql->get(\$list);
    }
    ") . "

    </code>");
    }

//------------------------------------------------ <  isAssoc > ---------------------------------------------------------
    private function _assoc(array $arr){
      if (array() === $arr) return false;
      return array_keys($arr) !== range(0, count($arr) - 1);
    }

//------------------------------------------------ <  _cache > ---------------------------------------------------------
    private function _cache() {
        $query_string = $this->query_string;

        if (!file_exists($_ENV['vsql_cache_dir'])) {
            mkdir($_ENV['vsql_cache_dir'], 0755, true);
        }

        if (empty($_ENV['vsql_cache_dir'])) {
            $this->_error_msg("The cache directory is not set : use \$_ENV['vsql_cache_dir'] = '/var/www/html/vsql_cache'; to declare it!");
        }

        $filename = 'def';
        $check_data = 0;

        $e = new Exception();
        foreach ($e->getTrace() as $key => $value) {
            if ($value['function'] == 'query') {
                $bodytag = str_replace(DIRECTORY_SEPARATOR, "", $value['file']);
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
            $data = json_decode(utf8_decode(file_get_contents($filename)), true);

            if (!isset($data[$this->id])) {
                /* if the id is not set in the file we add it */
                return $this->_save_json_cache($query_string, $data, $check_data, $filename);
            } else {

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
    private function _save_json_cache(   $query_string,  $data,   $date, $filename  ) {
        $chekd = $this->_quote_check(
        $this->_find_objects($query_string) , true);

        $data[$this->id] = array(
            'last_cache_update' => $date,
            'transformed' => $this->_transformed,
            'sql' => $chekd
        );

        $myfile = fopen($filename, "w");
        fwrite($myfile, json_encode($data));
        fclose($myfile);

        return $data[$this->id]['sql'];
    }

    private function _example_query(){
      return "SELECT

        art.*,
        TO_STD_VSQL( SELECT JAGG_VSQL(
           'id'     => s.id,
           'orders' => s.orders,
           'status' => s.status ,
           'items'  => ( SELECT JAGG_VSQL(
                  'id'      => id ,
                  'orders'  => orders,
                  'type'    => type,
                  'status'  => status,
                  'site'    => site,
                  'content' => content
        ) FROM items where id_section = s.id ))

        FROM section s WHERE s.id_article = art.id
        ) AS sections

      FROM articulos AS art
      WHERE TRUE ";
    }
}

// // ---------------------------------------------------------------------------------------------------------------------
// $_ENV["sql_host"] = 'localhost';
// $_ENV["sql_user"] = 'vas';
// $_ENV["sql_pass"] = 'dotravel';
// $_ENV["sql_db"] = 'dotravel';
// $_ENV["vsql_cache_dir"] = __DIR__;
//
// $db = new VSQL('','pretty');
//
// $db->query("SELECT
//   r.id_product,
//   COLLECTION_VSQL(
//       'id' => r.id,
//       'id_costumer' => r.id_customer,
//       'id_cartitem' => r.id_cartitem,
//       'title' => r.title,
//       'text' => r.text,
//       'date' => r.date,
//       'rating_valueformoney' => r.rating_valueformoney,
//       'rating_convenience' => r.rating_convenience,
//       'rating_accessibility' => r.rating_accessibility,
//       'rating_overall' => r.rating_overall,
//       'type_travel' =>  r.type_travel,
//       'display_name' => r.display_name,
//       'dotravel_rate' => r.dotravel_rate,
//       'status' => r.status
//     ) as json
// from reviews r
// where r.id_product = <:id_product>
// group by r.id_product
// ",array("id_product"=>1230),"dump_get");
//
// // ---------------------------------------------------------------------------------------------------------------------
