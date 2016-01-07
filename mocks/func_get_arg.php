<?php
function func_get_arg_mock(Emulator $emul,$arg_num)
{

	if (count($emul->trace)==0 or !isset(end($emul->trace)->args))
	{
		$emul->warning("func_get_arg():  Called from the global scope - no function context");
		return false;
	}
	elseif (count(end($emul->trace)->args)<=$arg_num)
	{
		$emul->warning("func_get_arg():  Argument {$arg_num} not passed to function");
		return false;
	}
	else
		return array_slice($emul->trace[count($emul->trace)-2]->args,$arg_num,1);
}
