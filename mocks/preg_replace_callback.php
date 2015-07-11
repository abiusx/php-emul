<?php

function preg_replace_callback_mock(Emulator $emul, $pattern, $callback, $subject ,$limit = -1, int &$count =null)
{
	$args=func_get_args();
	array_shift($args); //emul
	### DO NOT CHANGE ABOVE THIS LINE ###

	array_shift($args); //-pattern
	array_shift($args); //-callback
	
	array_unshift($args, function(array $matches) use ($emul,$callback) 
		{
			$argz=func_get_args();
			return $emul->call_function($callback,$argz);
		}); //+callback
	array_unshift($args,$pattern); //+pattern
	### DO NOT CHANGE BELOW THIS LINE ###
	$real_function=substr(__FUNCTION__,0,-strlen("_mock"));
	return call_user_func_array($real_function, $args);
}
