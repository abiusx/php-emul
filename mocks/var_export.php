<?php
function var_export_mock($emul,$expr,$return=false)
{
	ob_start();
	$args=func_get_args();
	$r=auto_mock(func_get_args(),__FUNCTION__);
	$emul->output(ob_get_clean());
	return $r;
}