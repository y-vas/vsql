# VASQL
-----
 add_modifiers

<br>

- adds global variables to the queries
example:

$vas = new VASQL();
$vas->add_modifiers(array("lang"=>1));
$vas->query("SELECT * FROM languages where id = <@lang> ");

result query would be =
        SELECT * FROM languages where id = 1
