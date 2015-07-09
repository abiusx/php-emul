<?php

function function_exists_mock(Emulator $emul,$name)
{
	return array_key_exists($name,$emul->functions);
}
