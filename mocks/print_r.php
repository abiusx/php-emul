<?php
function print_r_mock($emul,$expression,$return=false)
{
	$r=print_r($expression,true);
	if ($return) return $r;
	$emul->output($r);
	return true;
}