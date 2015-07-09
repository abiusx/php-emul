<?php

function header_mock(Emulator $emul,$string, $replace=true,$http_respnse_code=null)
{
	echo "\tHeader: {$string}",PHP_EOL;
}
