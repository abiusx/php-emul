<?php
function error_reporting_mock($emul,$level=null)
{
	return $emul->error_reporting($level);
}