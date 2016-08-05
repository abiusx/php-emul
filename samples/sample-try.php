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
	f(3,4);
	$u=6;
	define("something","9");
}
catch (Exception $e)
{
	echo hello(); //this is there
	var_dump($x);
	
	var_dump($z); //this is notice
	var_dump($u); //this is notice too, try elements are not available in catch, but definitions persist
	var_dump(defined("something")); //false
}