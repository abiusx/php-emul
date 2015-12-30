<?php

function curl_setopt_mock(Emulator $emul, $ch , /*int*/ $option  , $callback )
{
	$args=func_get_args();
	array_shift($args); //$emul
	### DO NOT CHANGE ABOVE THIS LINE ###
	$flag=false;
	foreach (get_defined_constants() as $k=>$v)
		if (preg_match("/CURLOPT_.*?FUNCTION/",$k) and $v===$option)
		{
			$flag=true;
			break;
		}
	if ($flag)
	$args[2]=function() 
			

	### DO NOT CHANGE BELOW THIS LINE ###
			use ($emul,$callback)  
			{
				// $argz=func_get_args();
				$argz=debug_backtrace()[0]['args']; //byref hack
				return $emul->call_function($callback,$argz);
			};
	$real_function=substr(__FUNCTION__,0,-strlen("_mock"));
	return call_user_func_array($real_function, $args);

}
