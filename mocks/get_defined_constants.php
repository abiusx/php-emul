<?php
function get_defined_constants_mock($emul,$categorize=false)
{
	if ($categorize)
		return array_merge(get_defined_constants(true),["user"=>$emul->constants]);
	else
		return array_merge(get_defined_constants(),$emul->constants);

}