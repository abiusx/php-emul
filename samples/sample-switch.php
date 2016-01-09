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
