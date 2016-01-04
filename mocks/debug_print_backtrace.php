<?php

function debug_print_backtrace_mock(Emulator $emul)
{
	return $emul->print_backtrace();
}
