<?php

// function array_map_mock(Emulator $emul,$callback,array $array1) #, ...
// {
// 	$args=func_get_args();
// 	array_shift($args); //emul
// 	array_shift($args); //callback
// 	array_unshift($args, function($n) use ($emul,$callback) 
// 		{
// 			$argz=func_get_args();
// 			return $emul->call_function($callback,$argz);
// 		});
// 	return call_user_func_array("array_map", $args);
// }