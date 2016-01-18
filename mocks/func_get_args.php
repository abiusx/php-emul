<?php
function func_get_args_mock(Emulator $emul)
{
	if (count($emul->trace)==0 or !isset($emul->trace[count($emul->trace)-2]->args))
	{
		$emul->warning("func_get_args():  Called from the global scope - no function context");
		return false;
	}
	else
		return $emul->trace[count($emul->trace)-2]->args;
}
