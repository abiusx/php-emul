<?php
/**
 * Does var_dump into output, because it is typically used for debugging
 * when testing emulator. However, the output is also returned as emulation output. 
 * @param  [type] $emul [description]
 * @param  [type] $x    [description]
 * @return [type]       [description]
 */
function var_dump_mock($emul)
{
	$args=func_get_args();
	array_shift($args);
	$emul->stash_ob();
	ob_start();
	call_user_func_array("var_dump", $args);
	$x=ob_get_clean();
	echo $x;
	$emul->restore_ob();
	$emul->output($x);
}