<?php
function headers_sent_mock($emul,$file=null,$line=null)
{
	$emul->verbose("Application is checking whether headers sent, emulator will say no...\n",3);
	return false;
	if ($emul->output)
		return false;
	else
		return false;
}