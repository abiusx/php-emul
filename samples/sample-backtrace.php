<?php
class CL 
{
	static function st()
	{
		var_dump(debug_backtrace());
	}
	function method($arg1=5)
	{
		var_dump(debug_backtrace());
	}
}
function f()
{
	var_dump(debug_backtrace());
}
f();
echo PHP_EOL;
CL::st();
echo PHP_EOL;
$a=new CL();
$a->method();
echo PHP_EOL;
call_user_func_array("f",[]);
