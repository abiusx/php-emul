<?php
// class MyDateNoWarning extends DateTime {}
// $o=new MyDateNoWarning();
// echo $o->getTimestamp(),PHP_EOL;

// class MyDateWarning extends DateTime {
// 	function __construct()
// 	{

// 	}
// }
// $o=new MyDateWarning();
// echo $o->getTimestamp(),PHP_EOL;


class MyDate extends DateTime
{

	function __construct()
	{
		parent::__construct();
	}
}
$mydate=new MyDate;

echo $mydate->getTimestamp(),PHP_EOL;

class MyPDO extends PDO
{


}

echo "all done.\n";
