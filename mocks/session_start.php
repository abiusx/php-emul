<?php
function session_start_mock($emul,$options=array())
{
	$emul->verbose("Attempting to start session, ignored by emulator, returning true...\n",3);
	$emul->data['session_id']=substr(md5(microtime()),10);
	$emul->data['session_started']=true;
	return true;
}
function session_status_mock($emul)
{
	if (isset($emul->data['session_started']))
		return PHP_SESSION_ACTIVE;
	else
		return PHP_SESSION_NONE;
}
function session_regenerate_id_mock($emul,$delete_old=false)
{
	$emul->data['session_id']=substr(md5(microtime()),10);
}