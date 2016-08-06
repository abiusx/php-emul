<?php
function defined_mock($emul,$constant_name)
{
	return $emul->constant_exists($constant_name);
	// return isset($emul->constants[$constant_name]) or isset(get_defined_constants()[$constant_name]);
}