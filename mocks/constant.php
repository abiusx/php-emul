<?php
function constant_mock($emul,$constant_name)
{
	return $emul->constant_get($constant_name);
	// if (isset($emul->constants[$constant_name]))
	// 	return $emul->constants[$constant_name];
	// else
	// 	return constant($constant_name);
}