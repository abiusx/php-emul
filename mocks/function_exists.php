<?php

function function_exists_mock(Emulator $emul,$name)
{
	return function_exists($name) or array_key_exists($name,$emul->functions);
}
