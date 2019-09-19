# VASQL
-----


adds global variables to the queries
example:

\n
$vas = new VASQL(); \n
$vas->add_modifiers(array("lang"=>1)); \n
$vas->query("SELECT * FROM languages where id = <@lang> "); \n
\n
result query would be = "SELECT * FROM languages where id = 1" \n
