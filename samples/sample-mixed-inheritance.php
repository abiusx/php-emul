<?php
class MyDateNoWarning extends DateTime {}
$o=new MyDateNoWarning();
echo "No warning, timestamp: ";
echo $o->getTimestamp(),PHP_EOL;

class MyDateWarning extends DateTime {
	function __construct()
	{

	}
}
$o=new MyDateWarning();
echo "Should see a warning, then timestamp: ";
echo $o->getTimestamp(),PHP_EOL;


class MyDate extends DateTime
{

	function __construct()
	{
		DateTime::__construct();
		echo DateTime::getTimestamp(),PHP_EOL;
	}
}
echo "Should see two timestamps: ";
$mydate=new MyDate;
echo $mydate->getTimestamp(),PHP_EOL;

class MyPDO extends PDO
{


}

echo "all done.\n";
