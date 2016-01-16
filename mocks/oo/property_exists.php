<?php
function property_exists_mock($emul,$object_or_string,$property)
{
	return $emul->property_exists($object_or_string,$property);
}