<?php
function func_num_args_mock(Emulator $emul)
{

	if (count($emul->trace)==0 or !isset(end($emul->trace)->args))
	{
		$emul->warning("func_num_args():  Called from the global scope - no function context");
		return -1;
	}
	else
		return count(end($emul->trace)->args);
}
