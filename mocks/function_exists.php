<?php

function function_exists_mock(Emulator $emul,$name)
{
	return $emul->function_exists($name);
	// return function_exists($name) or array_key_exists(strtolower($name),$emul->functions);
}
