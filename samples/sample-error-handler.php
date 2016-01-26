<?php
echo "-------------------------\n Should see: handler_all, handler1:msg, handler1, handler2:msg, handler_all:msg, real error, handler_all\n\n";
function handler_all($errno,$errstr)
{
	echo __FUNCTION__.":$errno-$errstr\n";
	return false;
}
function handler1($errno,$errstr)
{
	echo __FUNCTION__.":$errno-$errstr\n";
}
function handler2($errno,$errstr)
{
	echo __FUNCTION__.":$errno-$errstr\n";
}


$r=set_error_handler('handler_all');
echo $r,PHP_EOL;

$r=set_error_handler('handler1',E_USER_NOTICE);
echo $r,PHP_EOL;
trigger_error("Something something lightside",E_USER_NOTICE);

$r=set_error_handler('handler2',E_USER_WARNING);
echo $r,PHP_EOL;
trigger_error("Something something darkside",E_USER_WARNING);

restore_error_handler();
restore_error_handler();
trigger_error("this error should really happen!");
$r=set_error_handler('handler2',E_USER_WARNING);
echo $r,PHP_EOL;

for ($i=0;$i<10;++$i)
restore_error_handler();
