<?php
function get_class_mock($emul, $object=null)
{
	if ($object===null)
		return $emul->current_this;
	else
		return $emul->get_class($object);
}