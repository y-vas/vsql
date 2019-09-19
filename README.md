# VASQL
------------------------------------------------------


- Add global variables to the queries
example:

$vas = new VASQL();
$vas->add_modifiers(array("lang"=>1));
$vas->query("SELECT * FROM languages where id = <@T:lang> ");

result query would be = SELECT * FROM languages where id = 1

------------------------------------------------------
# CASES
- @S = fetch value from $SESSION
- @C = fetch value from $COOKIE
- @E = fetch value from $ENV
- @ or @T = fetch value from tags

-
