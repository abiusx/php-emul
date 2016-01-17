<?php
function is_a_mock($emul,$object_or_string,$class_name,$allow_string=false)
{
	return $emul->is_a($object_or_string,$class_name,$allow_string);
}