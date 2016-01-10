<?php
class CLS{

function f($a,$b)
{

}
}
$c=new CLS;
$c->f();

function f1($a,$b)
{
	echo count(func_get_args()),"=3",PHP_EOL;
	echo "Should be an array of 3: ";
	var_dump(func_get_args());
	echo $a,"=1",PHP_EOL;
	echo $b,"=2",PHP_EOL;
	$x=func_get_args()[2];
	echo $x,"=3",PHP_EOL;

}
function f2($a,$b=5)
{
	echo count(func_get_args()),"=1",PHP_EOL;
	echo "Should be array of 1: ";
	var_dump(func_get_args());
	echo $a,"=4",PHP_EOL;
	echo $b,"=5",PHP_EOL;
}
f1(1,2,3);

f2(4);