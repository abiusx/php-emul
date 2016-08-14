<?php

function register_shutdown_function_mock(Emulator $emul, $callback,$parameter=null)
{
	$args=func_get_args();
	array_shift($args); //emulator
	array_shift($args); //$callback
	$name=$callback;
	if(is_array($name))
	if (is_string($name[0]))
		$name=implode("::",$name);
	elseif($name[0] instanceof EmulatorObject)
		$name=$name[0]->classname."->".$name[1];
	else
		$name="Unknown::{$name[1]}";
	$emul->verbose("Registering shutdown function '{$name}'...\n",4);
	$emul->shutdown_functions[]=(object)array("callback"=>$callback,"args"=>$args);
}
