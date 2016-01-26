<?php
function spl_autoload_register_mock($emul,$callback=null,$throw=true,$prepend=false)
{
	return $emul->spl_autoload_register($callback,$throw,$prepend);
}
function spl_autoload_unregister_mock($emul,$function)
{
	return $emul->spl_autoload_unregister($function);
}
function spl_autoload_extensions_mock($emul,$ext=null)
{
	return $emul->spl_autoload_extensions($ext);
}
function spl_autoload_functions_mock($emul)
{
	return $emul->spl_autoload_functions();
}
function spl_autoload_call_mock($emul,$class)
{
	return $emul->spl_autoload_call($class);
}