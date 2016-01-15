<?php
function var_dump_mock($emul,$expression=null)
{
	ob_start();
	$args=func_get_args();
	$r=auto_mock(func_get_args(),__FUNCTION__);
	$emul->output(ob_get_clean());
}