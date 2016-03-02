<?php

function call_user_func_array_mock(Emulator $emul,  $callback , array $param_arr )
{
	return $emul->call_function($callback,$param_arr);
	// $callback=function() 
	// 		use ($emul,$callback)  
	// 		{
	// 			// $argz=func_get_args();
	// 			$argz=debug_backtrace()[0]['args']; //byref hack
	// 			return $emul->call_function($callback,$argz);
	// 		};
	// return call_user_func_array($callback, $param_arr);
}
