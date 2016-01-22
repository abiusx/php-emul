<?php
function get_object_vars_mock($emul,$object)
{
	if (!($object instanceof EmulatorObject)) return get_object_vars($object);

	return $object->properties;
}