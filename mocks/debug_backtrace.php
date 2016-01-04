<?php

function debug_backtrace_mock(Emulator $emul)
{
	return $emul->backtrace();
}
