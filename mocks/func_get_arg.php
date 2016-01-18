<?php
function func_get_arg_mock(Emulator $emul,$arg_num)
{

	if (count($emul->trace)==0 or !isset($emul->trace[count($emul->trace)-2]->args))
	{
		$emul->warning("func_get_arg():  Called from the global scope - no function context");
		return false;
	}
	elseif (count($emul->trace[count($emul->trace)-2]->args)<=$arg_num)
	{
		$emul->warning("func_get_arg():  Argument {$arg_num} not passed to function");
		return false;
	}
	else
		return array_slice($emul->trace[count($emul->trace)-2]->args,$arg_num,1);
}
