<?php 
function set_error_handler_mock($emul,$handler,$error_reporting=32767)
{
	return $emul->set_error_handler($handler,$error_reporting);
}