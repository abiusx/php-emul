<?php
echo "-------------------------\n Exceptions terminate. Can't test all at one run.\n\n";
function handler_all($e)
{
	echo __FUNCTION__.":$e\n";
}
function handler1($e)
{
	echo __FUNCTION__.":".$e."\n";
}


$r=set_exception_handler('handler_all');
echo $r,PHP_EOL;

//phase 1
// $r=set_exception_handler('handler1');
// echo $r,PHP_EOL;
// throw new Exception("Something something lightside.");

//phase 2
try {
	throw new Exception("Something something darkside.");
}
catch (Exception $e)
{
	echo "Good. this should be seen\n";
}
for ($i=0;$i<10;++$i)
	restore_exception_handler();
throw new Exception("this error should really happen!");
