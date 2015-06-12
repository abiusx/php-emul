<?php
declare(ticks=500);
$a=-3;
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
		echo "No match! should not output.",PHP_EOL;
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
for ($i=0;$i<100;++$i)
{
	if ($i&1)
		continue;
	$s++;
}
echo $s," should be 50",PHP_EOL;

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