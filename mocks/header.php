<?php

function header_mock(Emulator $emul,$string, $replace=true,$http_respnse_code=null)
{
	$emul->verbose("Header: {$string}".PHP_EOL,4);
}
