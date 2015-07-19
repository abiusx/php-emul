<?php
function func_get_args_mock(Emulator $emul)
{
	if (count($emul->trace)==0 or !isset(end($emul->trace)->args))
		$emul->error("func_get_args():  Called from the global scope - no function context");
	else
		return end($emul->trace)->args;
}
