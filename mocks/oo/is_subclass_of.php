<?php
function is_subclass_of_mock($emul,$object_or_string,$class_name,$allow_string=true)
{
	return $emul->is_subclass_of($object_or_string,$class_name,$allow_string);
}