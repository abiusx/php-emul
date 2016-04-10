<?php
echo "Testing array append... Should get array(2) and then array(2=>array(array(3)))",PHP_EOL;
$t[]=2;
var_dump($t);
$z=array();
$z[2][][]=3;
var_dump($z);


echo "Testing List... Should get array(1,2) then int(1) and int(2)",PHP_EOL;
$a=[1,2];
// var_dump(list($x,$y)=$a);

var_dump($x);
var_dump($y);

echo "Testing eval...",PHP_EOL;
eval("echo 'hello there!',PHP_EOL;");

echo "Testing global...",PHP_EOL;
function f2()
{
	global $something;
	$something=5;
}
f2();
echo "5=",$something,PHP_EOL;

unset($t);
$t[0][1][2][3]=4;
echo $t[0][1][2][3],"=4",PHP_EOL;
echo "Testing static variables...",PHP_EOL;
function static_f()
{
	static $x=0;
	echo $x++,PHP_EOL;
}
echo "0=",static_f();
echo "1=",static_f();
echo "2=",static_f();

function a()
{
	if (1)
		if (!2)
		return 2;
	else 
		return 3;

	return 1;
}
function fibo($n)
{
	if ($n<2) return 1;
	return fibo($n-1)+fibo($n-2);
}
echo a(),"=3",PHP_EOL;
echo fibo(10),"=89",PHP_EOL;
declare(ticks=500);
$a=-3;
echo isset($a),"=1",PHP_EOL;
    // if (!preg_match("/charset=([a-zA-Z0-9\-]+)/",$transarray,$match)) //$match is byref here, it shouldn't work in emulator
var_dump(function_exists("f"));
function f($x)
{

	switch ($x)
	{
		case 5:
		echo "Match",PHP_EOL;
		break;
		case 7:
		echo "No match!",PHP_EOL;
		default:
		echo "Default",PHP_EOL;
	}
}
echo "Match=",f(5);

echo "Default=",f(3);

echo "No Match,Default=",f(7);

for ($i=0;$i<1;++$i)
{
	echo "you should see this and",PHP_EOL;

	echo "this",PHP_EOL;

	break;

	echo "But not this",PHP_EOL;

}
for ($i=0;$i<2;++$i)
{
	echo "you should see me twice",PHP_EOL;
	continue;
	echo "but definitely not me",PHP_EOL;
}



for ($i=0;$i<100;++$i)
{
	if ($i>5) break;
}
echo "6=",$i,PHP_EOL;

echo "fine=";
try {
	echo "fine",PHP_EOL;
}
catch (Exception $e)
{
	echo "No catch",PHP_EOL;
}
echo "proper catch=";
try {
	throw new Exception("just an exception. you shouldn't see this.");
}
catch (Exception $e)
{
	echo "proper catch",PHP_EOL;
}
echo "HTML=";
?>some inline html
<?php
$t=1;
function somefunc()
{
	global $t;
	echo $t, " should be 1",PHP_EOL;
}
somefunc();


$s=0;
for ($i=0;$i<10;++$i)
{
	if ($i&1)
		continue;
	$s++;
}
echo $s," should be 5",PHP_EOL;

const ABC="abc";
echo ABC, " should be abc",PHP_EOL;

for ($j=0;$j<100;++$j)
for ($i=0;$i<100;++$i)
	if ($i>10 and $j>2) break 2;
echo "11,3=",$i,",",$j,PHP_EOL;


for ($j=0;$j<2;++$j)
{

	echo "should see this, and one more",PHP_EOL;
	for ($i=0;$i<2;++$i)
	{
		echo "which is this",PHP_EOL;
		break 2;
		echo "but definitely not this.",PHP_EOL;
	}
	echo "and of course not this",PHP_EOL;

}

echo "123=";
for ($k=0;$k<5;++$k)
{
	echo "1";
	for ($j=0;$j<5;++$j)
	{

		echo "2";
		for ($i=0;$i<5;++$i)
		{
			echo "3";
			break 3;
			echo "*";
		}
		echo "^";

	}
	echo "&";
}
echo PHP_EOL;


echo "you should see j,i,j,i:",PHP_EOL;
for ($j=0;$j<2;++$j)
{

	echo "\tj loop",PHP_EOL;
	for ($i=0;$i<2;++$i)
	{
		echo "\ti loop",PHP_EOL;
		continue 2;
		echo "nada i loop",PHP_EOL;
	}
	echo "nada j loop",PHP_EOL;

}
echo "you should see kjikji: ";
for ($k=0;$k<2;++$k)
{
	echo "k";
	for ($j=0;$j<2;++$j)
	{
		echo "j";
		for ($i=0;$i<2;++$i)
		{
			echo "i";
			continue 3;
			echo "#";
		}
		echo "@";

	}
	echo "!";
}
echo PHP_EOL;

$GLOBALS['abc']='hello';
echo "hello=",$abc,PHP_EOL;

function temp()
{
	global $abc;
	$abc.="a";
	echo $abc,"=helloa",PHP_EOL;
}
temp();
echo "helloa=",$abc,PHP_EOL;