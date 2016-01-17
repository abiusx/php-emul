<?php
function is_callable_mock($emul,  $var ,$syntax_only = false , string &$callable_name =null)
{
	if ($syntax_only)
		return is_callable($var,true,$callable_name);
	if (!is_callable($var,true,$callable_name)) return false;
	return $emul->is_callable($var);
}