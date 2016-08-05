<?php
$x=1;
$y=2;

function f($arg1,$arg2)
{
	$z=5;
	throw new Exception;
}
try {
	function hello() {return 1;}
	$u=6;
	echo $u,"=6\n";
	define("something","9");
	f(3,4);
}
catch (Exception $e)
{
	echo hello(); //this is there
	var_dump($x);
	
	echo "this should fail: ";
	var_dump($z); //this is notice
	var_dump($u); 
	var_dump(defined("something")); //false
}

/// more complicated test:

function f2($a1,$a2)
{
	function f3($a3,$a4)
	{
		throw new Exception;
	}
	$h=7;
	return f3($a2,$a1);
}
echo str_repeat("-",80),PHP_EOL;
try {

	$outer=999;
	try {
		echo $outer,PHP_EOL;
		f2(0,0);
	}
	catch (RuntimeException $e) //no catch
	{
		echo "This should not run\n";
	}

}
catch (Exception $e)
{
	catch_block($e,$x);

	echo "Now there should be a terminal error:\n";
	throw new Exception; //should err, but not terminate emulator
}
function catch_block($e,$o)
{
	echo "This should run and say 1: ";
	echo $o,PHP_EOL;

}