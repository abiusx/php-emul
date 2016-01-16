<?php
function method_exists_mock($emul,$object_or_string,$method_name)
{
	return $emul->method_exists($object_or_string,$method_name);
}