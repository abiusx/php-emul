<?php
function get_class_mock($emul, $object=null)
{
	if ($object===null)
		$object=$emul->current_this;
	return $emul->get_class($object);
}