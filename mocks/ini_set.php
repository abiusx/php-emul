<?php
function ini_set_mock($emul,$varname,$value)
{
	if ($varname=="memory_limit")
		return false;
	if ($varname=="display_errors")
		return false;
	#TODO: support for display_errors
	if ($varname=="max_execution_time")
		return false;
	#TODO: support for execution time limit
	
	return ini_set($varname,$value);	
}