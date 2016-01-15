<?php
function vprintf_mock($emul,$format)
{
	ob_start();
	$r=auto_mock(func_get_args(),__FUNCTION__);
	$emul->output(ob_get_clean());
	return $r;
}

