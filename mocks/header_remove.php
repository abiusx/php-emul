<?php

function header_remove_mock(Emulator $emul,$string)
{
	$emul->verbose("Header removed: {$string}".PHP_EOL,4);
}
