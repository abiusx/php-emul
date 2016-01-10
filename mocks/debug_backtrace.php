<?php

function debug_backtrace_mock(Emulator $emul,$options=0,$limit=0)
{
	return $emul->backtrace($options,$limit);
}
