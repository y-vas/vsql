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

      $_SESSION["vsql"] = "Vasyl Yovdiy";
      $this->_superglobals("env_",$_ENV);
      $this->_superglobals("ses_",$_SESSION);
      $this->_superglobals("coo_",$_COOKIE);

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

//------------------------------------------------ <  add_global_vars > ------------------------------------------------
  function env(array $params){
		$this->modifiers = array_merge($this->modifiers, $params );
	}


//------------------------------------------------ <  add_superglobals > -----------------------------------------------
  private function _superglobals(string $starter,array $params){
		foreach ($params as $key => $value) {
      $this->modifiers[$starter.$key] = $value;
    }
	}


//------------------------------------------------ <  query > ----------------------------------------------------------
  function query(string $query_string, array $query_vars) :string {

    preg_match_all('!<(.*?)>!', $query_string, $_modifiers_match);

    // foreach ($_modifiers_match[1] as $mkey => $modifier_tag) {
    //   $modif = $this->_convert_modifier($modifier_tag);
    //   $query_string = str_replace( $_modifiers_match[0][$mkey] ,$modif, $query_string);
    // }
    //
    // //-------------------------------------------------------------
    // $_var_count = count($query_vars);
    //
    // if($_var_count != preg_match_all('!%[sSiIfFcClLqQnNtT]!', $query_string, $_match)) {
    //   $this->_error_msg('unmatched number of vars and % placeholders: ' . $query_string);
    // }
    //
    //
    // // get string position for each element
    // $_var_pos = array();
    // $_curr_pos = 0;
    //
    // for( $_x = 0; $_x < $_var_count; $_x++ ) {
    //   $_var_pos[$_x] = strpos($query_string, $_match[0][$_x], $_curr_pos);
    //   $_curr_pos = $_var_pos[$_x] + 1;
    // }
    //
    // // build query from passed in variables, escape them
    // // start from end of query and work backwards so string
    // // positions are not altered during replacement
    //
    // $_last_removed_pos = null;
    // $_last_var_pos = null;
    //
    // for( $_x = $_var_count-1; $_x >= 0; $_x-- ) {
    //
    //   if( isset($_last_removed_pos) && $_last_removed_pos < $_var_pos[$_x] ) {
    //       continue;
    //   }
    //
    //   // escape string
    //   $query_vars[$_x] = $this->_sql_escape($query_vars[$_x]);
    //
    //
    //   if(in_array($_match[0][$_x], array('%S','%I','%F','%C','%L','%Q','%N',"%T"))) {
    //
    //     // get positions of [ and ]
    //     $_right_pos = strpos($query_string, ']', isset($_last_var_pos) ? $_last_var_pos : $_var_pos[$_x]);
    //
    //     // no way to get strpos from the right side starting in the middle
    //     // of the string, so slice the first part out then find it
    //
    //     $_str_slice = substr($query_string, 0, $_var_pos[$_x]);
    //     $_left_pos = strrpos($_str_slice, '[');
    //
    //     if($_right_pos === false || $_left_pos === false) {
    //       $this->_error_msg('missing or unmatched brackets: ' . $query_string);
    //     }
    //     if(in_array($query_vars[$_x], $this->_drop_values, true)) {
    //                   $_last_removed_pos = $_left_pos;
    //       // remove entire part of string
    //       $query_string = substr_replace($query_string, '', $_left_pos, $_right_pos - $_left_pos + 1);
    //                   $_last_var_pos = null;
    //               } else if ($_x > 0 && $_var_pos[$_x-1] > $_left_pos) {
    //                   // still variables left in brackets, leave them and just replace var
    //                   $_convert_var = $this->_convert_var($query_vars[$_x], $_match[0][$_x]);
    //       $query_string = substr_replace($query_string, $_convert_var, $_var_pos[$_x], 2);
    //                   $_last_var_pos = $_var_pos[$_x] + strlen($_convert_var);
    //     } else {
    //       // remove the brackets only, and replace %S
    //       $query_string = substr_replace($query_string, '', $_right_pos, 1);
    //       $query_string = substr_replace($query_string, $this->_convert_var($query_vars[$_x], $_match[0][$_x]), $_var_pos[$_x], 2);
    //       $query_string = substr_replace($query_string, '', $_left_pos, 1);
    //       $_last_var_pos = null;
    //     }
    //   } else {
    //     $query_string = substr_replace($query_string, $this->_convert_var($query_vars[$_x], $_match[0][$_x]), $_var_pos[$_x], 2);
    //   }
    // }


    return $query_string;
  }

}

$vas = new VSQL();
$vas->env(array("lang" => 1));


/* ----Helpers---- */
$conversation = "SELECT id_conversation FROM support_labels sl
                 LEFT JOIN support_conversations sc ON sc.id = sl.id_conversation
                 WHERE label = CONCAT('#book-',ci.id)";

$subproduct = "SELECT ps.id_subproduct FROM product_sold ps WHERE ps.id = ci.id_sold";
$product = "SELECT id_product FROM sub_product sp WHERE sp.id = ($subproduct)";
$customer = "SELECT id_customer from carts c where ci.id_cart = c.id limit 1";
$product_meta = "SELECT metavalue FROM product_meta pm WHERE pm.id_product = ($product) AND pm.metaname";
$cart_meta = "SELECT metavalue FROM cart_meta WHERE id_cart = ci.id_cart AND metaname";
$cartitem_meta = "SELECT metavalue FROM cartitem_meta WHERE id_cartitem = ci.id AND metaname";
$customer_meta = "SELECT metavalue FROM customer_meta WHERE id_customer = ($customer) AND metaname";


$vas->query("SELECT *
[ , (SELECT t.name FROM type t WHERE t.id = (SELECT sp.type FROM sub_product sp WHERE sp.id = ($subproduct))) as %S ]
[ , (SELECT CONCAT(date, ' ', hour) FROM product_sold WHERE id = ci.id_sold) as %S ]
[ , (SELECT date FROM carts WHERE id = ci.id_cart) as %S ]
[ , (CONCAT((SELECT firstname FROM customers where id = ($customer)),' ', (SELECT lastname FROM customers where id = ($customer)) )) as %S ]
[ , (SELECT email FROM customers where id = ($customer)) as %S ]
[ , (SELECT REPLACE(phone,'|',' ') FROM customers where id = ($customer)) as %S ]
[ , (SELECT city from products p where p.id = ($product) limit 1) as %S ]
[ , (SELECT name from suppliers s where ci.id_supplier = s.id limit 1) as %S ]
[ , ($customer) as %S /*gets the customer id */ ]
[ , ($customer_meta = 'avatar' limit 1) as %S /*gets the customer avater */ ]
[ , (SELECT id from supplier_users su where ci.id_supplier = su.id_supplier limit 1) as %S /* gets the first supplier in cart */ ]
[ , (SELECT name FROM status sta WHERE sta.id = ci.status limit 1) as %S /*gets the cart status */]
[ , ($product_meta = 'title' limit 1) as %S /*gets the product title*/ ]
[ , ($product_meta = 'supplieremail' limit 1 ) as %S /* gets the product supplieremail */ ]
[ , ($product_meta = 'departure-number-1' limit 1 ) as %S /* gets the product supplier phone */ ]
[ , ($product_meta = 'departure-return-1' limit 1 ) as %S /* gets the cart return point */ ]
[ , ($product_meta = 'latitude' limit 1 ) as %S /* gets product meet point latitude */ ]
[ , ($product_meta = 'longitude' limit 1 ) as %S /* gets product meet point longitude */ ]
[ , ($product) as %S /* gets the product id*/ ]
[ , (SELECT name FROM status sta WHERE sta.id = (SELECT status from products p where p.id = ($product) limit 1)) as %S /*product status */]
[ , (SELECT concat(path,name) FROM product_photos ph where ph.id_product = ($product) order by show_order limit 1) as %S /* gets the product first image*/ ]
[ , (SELECT t.name FROM products p left join type t on t.id = p.product_type where p.id = ($product)) as %S /* gets the product type */ ]

-- gives you an existing conversation with the type of users for the cart item
[ , ($conversation and sc.id_admin_user is not null and sc.id_supplier_user is not null limit 1) as %S ]
[ , ($conversation and sc.id_admin_user is not null and sc.id_customer is not null limit 1) as %S ]
[ , ($conversation and sc.id_supplier_user is not null and sc.id_customer is not null limit 1) as %S ]

[ , CONCAT( ci.id_cart * (SELECT st_value FROM setting WHERE st_group = 'staticnumber' and st_key ='referenceCart' limit 1),'-',ci.id ) as %S /*gives you the factor of the cart */ ]
[ , (SELECT r.id FROM reviews r where r.id_cartitem = ci.`id` limit 1) as %S /*gives you the a review made on this cartitem*/ ]

[ , ($cartitem_meta = 'personId' limit 1) as %S ]
[ , ($cartitem_meta = 'currency' limit 1) as %S ]
[ , ($cart_meta = 'paid_by' limit 1) as %S ]
 FROM `cart_items` ci WHERE TRUE
 [ AND (SELECT id_customer from carts c where ci.id_cart = c.id limit 1) = %I ]
 [ AND ci.`id`            = %I ]
 [ AND ci.`id_cart`       = %I ]
 [ AND ci.`id_supplier`   = %I ]
 [ AND ci.`title`         = %N ]
 [ AND ci.`retail_amount` = %N ]
 [ AND ci.`net_amount`    = %N ]
 [ AND ci.`url_crypte`    = %N ]
 [ AND ci.`id_sold`       = %I ]
 [ AND ci.`status`        = %N ]
 [ AND CONCAT( ci.id_cart * (SELECT st_value FROM setting WHERE st_group = 'staticnumber' and st_key ='referenceCart' limit 1),'-',ci.id ) = '%S' ]
 [ AND ($cart_meta = 'email' limit 1) like '%%S%' ]
 [ AND (CONCAT(($cart_meta = 'firstname' limit 1),' ', ($cart_meta = 'lastname' limit 1))) like '%%S%' ]
 [ AND (SELECT id_language FROM product_meta pm WHERE pm.id_product = ($product) AND pm.`metaname` = 'title') = %I ]
 [ ORDER BY `%S` ][ %S ]
 [ LIMIT %I ][ , %I ] [ OFFSET %I ]
",array());



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
