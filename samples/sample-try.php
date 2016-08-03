<?php
$x=1;
$y=2;

function f($arg1,$arg2)
{
	$z=5;
	throw new Exception;
}
try {
	f(3,4);

}
catch (Exception $e)
{
	var_dump($x);
	var_dump($z);
}