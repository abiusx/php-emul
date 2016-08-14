<?php
function session_id_mock($emul,$id=null)
{
	if ($id)
		$emul->data["session_id"]=$id;
	if (isset($emul->data["session_id"]))
		return $emul->data["session_id"];
	else
		return "";
}