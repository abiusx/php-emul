<?php
class the_class {
	static function static_f()
	{
		return true;
	}
}
echo(the_class::static_f()),"=1",PHP_EOL;
echo (call_user_func(array("the_class","static_f"),[])), "=1",PHP_EOL;

class cls
{
	function a()
	{
		return 3;
	}
	function b()
	{
		return $this;
	}
}

function f()
{
	return new cls();
}
function f2()
{
	return range(0,10);
}
function f3($x)
{
	echo count($x);
}


echo f2()[5],"=5",PHP_EOL;
echo f()->a(),"=3",PHP_EOL;
echo f()->b()->a(),"=3",PHP_EOL;

// echo f3(f2()),"=11",PHP_EOL;

function wp_scripts() {
	global $wp_scripts;
	if ( ! ( $wp_scripts instanceof cls ) ) {
		$wp_scripts = new cls();
	}
	return $wp_scripts;
}
$a=null;
echo wp_scripts()->a( $a ),"=3",PHP_EOL;

echo $wp_scripts->a(),"=3\n";
