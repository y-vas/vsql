<?php
namespace VSQL\VSQL;
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'DB.php');


//                                           ██╗     ██╗ ███████╗  ██████╗  ██╗
//                                           ██║    ██║ ██╔════╝ ██╔═══██╗ ██║
//                                           ██║   ██║ ███████╗ ██║   ██║ ██║
//                                          ╚██╗  ██║ ╚════██║ ██║▄▄ ██║ ██║
//                                           ╚████╔╝  ███████║╚ ██████║ ███████╗
//                                             ╚═══╝   ╚══════╝ ╚══▀▀═╝ ╚══════╝

class VSQL extends DB {

//------------------------------------------------ <  query > ----------------------------------------------------------
    public function query($query_string, $query_vars, $debug = "") {
        $this->query_original = $query_string;
        $this->query_vars = $query_vars;
        $this->query_string = $query_string;

        $cache = "";
        if (!empty($this->id)) {
          $cache = $this->_cache();
        }

        if (empty($cache)) {
          $query_string = $this->_find_objects($query_string);
        }

        if (empty($cache)) {
          $query_string = $this->_quote_check($query_string);
          $query_string = $this->_var_transform($query_string);
        } else {
          $query_string = $this->_var_transform($cache);
        }

        $this->query_string = $query_string;

        $this->_inspect($debug);

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
                $this->_error_msg(implode(array()));
                break;

            case 'debug':
                $this->_error_msg(" DEBUG ");
                break;

            case 'dump_get':
                ob_start();
                var_dump($this->get(isset($extra[1])));
                $result = ob_get_clean();
                $this->_error_msg("<strong>VAR DUMP</strong> : <br> <code class='scss'> $result </code>");
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
                $this->_transformed[$name] = ['array-std'];
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
        $query_string,
        $return_empty_if_has_null_values = false
    ) {
        preg_match_all('!{(.*?)?(\!)?:(.*?)}!', $query_string, $match);


        foreach ($match[1] as $key => $simbol) {
            $var_key = $match[3][$key];
            $exp = explode(';',$var_key);
            $var_key = $exp[0];
            $not_null = $match[2][$key];

            $var = $this->_convert_var($simbol, $var_key);

            if ($var == null) {

                if (count($exp) > 1){
                  $var = $exp[1];
                }

                if ($not_null == "!") {
                    $this->_error_msg("$var_key key resulted in null!");
                }

                if ($return_empty_if_has_null_values) {
                    return "";
                }

            }

            if (strpos($simbol, 'i') !== false && empty($var)){
              $var = 0;
            }

            $query_string = str_replace($match[0][$key], $var, $query_string);
        }

        return $query_string;
    }

//--------------------------------------------- <  _quote_check > ------------------------------------------------------
    private function _quote_check($query_string, $cache = false ) {
        preg_match_all("!{{([\w*?:\!]*)(\X*?)}}!", $query_string, $match_brakets);

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

            preg_match_all("!{{([\w*?:\!]*)(\X*?)}}!", $query_string, $match_brakets);
        }


        return $query_string;
    }

//------------------------------------------------ <  _get_var_from_query_vars > ---------------------------------------
    private function _qvar( $var ) {
        return empty($this->query_vars[$var]) ? null : $this->query_vars[$var];
    }

//------------------------------------------------ <  _get_var_from_query_vars > ---------------------------------------
    private function _tvar( $var ) {
        return empty($this->tags[$var]) ? null : $this->tags[$var];
    }

//------------------------------------------------ <  _convert_var > ---------------------------------------------------
    private function _convert_var( $type, $var ) {

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
                $result = $this->secure($result);
                break;

            // @E = fetch value from $_COOKIE
            case '@c':
            case '@C':
                $result = empty($_COOKIE[$var]) ? null : $_COOKIE[$var];
                $result = $this->secure($result);
                break;

            // @E = fetch value from $_SESSION
            case '@s':
            case '@S':
                $result = empty($_SESSION[$var]) ? null : $_SESSION[$var];
                $result = $this->secure($result);
                break;

            // cast to integer
            case 'i':
            case 'I':
                $x = $this->_qvar($var);
                if ($x != null) {
                    settype($x, 'integer');
                    $result = $this->secure($x);
                }
                break;

            // cast to float
            case 'f':
            case 'F':
                $x = $this->_qvar($var);
                settype($x, 'float');
                $result = $this->secure($x);
                break;

            // implode the array
            case 'implode':
                $x = $this->_qvar($var);
                $res = $this->secure(implode(',', $x));
                $result = $res != null ? "'" . $res . "'" : $res;
                break;

            case 'array':
                $x = $this->_qvar($var);
                $result = $x != null ? $this->secure(implode(',', $x)) : '';
                break;

            // trims the value
            case 't':
            case 'T':
                $result = $this->secure(trim($this->_qvar($var)));
                break;

            // transforms the value to string
            case 's':
            case 'S':
                $res = $this->secure(trim($this->_qvar($var)));
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

//------------------------------------------------ <  _ecape_qvar > ----------------------------------------------------
    private function _escape_qvar( $var ) {
        return $this->secure($this->_qvar($var));
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

                    if($result = mysqli_store_result($mysqli)) {
                        while ($proceso = mysqli_fetch_assoc($result)) {
                            $rt = $this->_fetch_row($result, $proceso);
                            if ($type == "array") { $obj[$nr] = $rt; }
                            else { $obj->$nr = $rt; }
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
                else { $obj = $this->_fetch_row($result, $proceso); }
            };

        } else {
            $this->_error_msg("Fail on query get :" . mysqli_error($mysqli));
        }

        return $obj;
    }

//------------------------------------------------ <  run > ------------------------------------------------------------
    public function run() {
        $mysqli = $this->CONN;

        $mysqli->query($this->query_string);

        return $mysqli;
    }

// ------------------------------------------------ <  _fetch_row > ----------------------------------------------------
    private function _fetch_row( $result, $proceso ) {
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
    public function _transform_get( $val, $datatype, $key ) {
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
        settype($val, $dt_str);

        foreach ($this->_transformed as $k => $value) {
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

          case 'array-std':
              $non = json_decode($val,true);
              if ($non==null){
                $non = json_decode(utf8_decode($val), true);
              }
              foreach ($non as $key => $value) {
                $non[$key] = (object) $value;
              }
              return $non;
        }
        return $val;
    }

//------------------------------------------------ <  makemodel > ------------------------------------------------------
    private function _mkfunction( $table, $fun ) {

        $this->query("SHOW COLUMNS FROM <!:tb> FROM <@E!:vsql_database> ", array('tb' => $table));

        $vals = $this->get(true);

        switch ($fun) {
            case 'select':
                $this->_sel($vals, $table);
                break;
        }

    }

}
