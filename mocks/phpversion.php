<?php
function phpversion_mock($emul,$extension=null)
{
	if (isset($extension))
		return phpversion($extension);
	else
		if ($emul->max_php_version>=phpversion())
			return phpversion();
		else
			return $emul->max_php_version;
}