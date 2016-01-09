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

echo "5=",var_dump(f(5));
