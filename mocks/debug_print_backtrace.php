<?php

function debug_print_backtrace_mock(Emulator $emul)
{
	$emul->output($emul->print_backtrace());
}
