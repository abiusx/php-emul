<?php

function debug_print_backtrace_mock(Emulator $emul)
{
	print_r($emul->trace);
}
