<?php
function get_parent_class_mock($emul,$object=null)
{
	if ($object===null)
		return $emul->get_parent_class($emul->current_this);
	else
		return $emul->get_parent_class($object);

}