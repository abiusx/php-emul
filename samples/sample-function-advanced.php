<?php

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


f1(1,2,3);
