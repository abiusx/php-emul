<?php

function array_walk_mock(Emulator $emul, array &$array , $callback , $userdata = NULL )
{
	$args=func_get_args();
	array_shift($args); //$emul
	### DO NOT CHANGE ABOVE THIS LINE ###
	$args[0]=&$array;
	$args[1]=function(&$item,$key,$userdata=null) 
			//TODO: byref support, item can be byref!!
			

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
