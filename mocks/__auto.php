<?php
/**
 * When a mock function wants to call its original counterpart, it can use 
 * $r=auto_mock(func_get_args(),__FUNCTION___);
 */
function auto_mock($args,$function)
{
	if (substr($function,-5)=="_mock")
		$function=substr($function,0,strlen($function)-5);
	$emul=array_shift($args);
	$res=call_user_func_array($function,$args);
	return $res;
}
