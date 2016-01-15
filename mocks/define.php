<?php
function define_mock($emul,$constant_name,$value,$case_insensitivity=false)
{
	if ($case_insensitivity)
		$constant_name=strtolower($constant_name);
	if (isset($emul->constants[$constant_name]))
	{
		$emul->notice("Constant {$constant_name} already defined");
		return false;
	}
	$emul->constants[$constant_name]=$value;
	return true;
}