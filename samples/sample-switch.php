<?php

function f($x)
{
	switch($x)
	{

	case 5:
	case 6:
		if (true)
			return 5;
		return false;
	case 9:
	var_dump("nonono!");

	case 7:
		return false;
	default:
		var_dump("never run");
	}
	return false;

}
function f2()
{
	for ($i=0;$i<10;++$i)
	{
		return $i;
	}
}
echo "5=",var_dump(f(5));
echo "0=",var_dump(f2(5));



echo PHP_EOL;
foreach (range(1,6) as $x)
{

	switch($x)
	{
		case 1:
			$x*=1;
		case 2:
			$x*=2;
		case 3:
			$x*=3;
			break;
		case 4:
			$x*=4;
		case 5:
			$x*=5;
		default:
		$x*=10;
	}

	var_dump($x);
}
