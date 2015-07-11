<?php

function array_map_mock(Emulator $emul,$callback,array $array1) #, ...
{
	$args=func_get_args();
	array_shift($args); //emul
	array_shift($args); //callback
	array_unshift($args, function($n) use ($emul,$callback) 
		{
			$args=func_get_args();
			return $emul->call_function($callback,$args);
		});
	return call_user_func_array("array_map", $args);

	// return array_map(function($n) use ($emul,$callback) 
	// 	{
	// 		$args=func_get_args();
	// 		return $emul->call_function($callback,$args);
	// 	},$args[0]); //make this dynamic too
}
