<?php


class Base {
	function __construct($arg1=5,$arg2=10)
	{
		echo "Base::construct\n";
		var_dump($arg1,$arg2);
		print_r(func_get_args());
	}
}

class Extend extends Base {

	// function __construct()
	// {
	// 	echo "Extend::construct\n";
	// }
}

$x=new Extend;
