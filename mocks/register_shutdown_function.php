<?php

function register_shutdown_function_mock(Emulator $emul, $callback,$parameter=null)
{
	$args=func_get_args();
	array_shift($args); //emulator
	array_shift($args); //$callback
	$emul->shutdown_functions[]=(object)array("callback"=>$callback,"args"=>$args);
}
