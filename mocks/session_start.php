<?php
function session_start_mock($emul,$options=array())
{
	$emul->verbose("Attempting to start session, ignored by emulator, returning true...\n",3);
	$emul->data['session_id']=substr(md5(time()),10);
	return true;
}
