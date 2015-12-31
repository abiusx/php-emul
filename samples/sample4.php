<?php
//references sample
$x=[];
$x['hello']=1;
// echo isset($x['hello']);
// exit(0);
$s="echo 'hi';";
eval($s);


$a=1;
$b=&$a;
$b=5;

echo $a;

function swap(&$a,&$b,$c=0)
{
	$c=$a;
	$a=$b;
	$b=$c;
}

$c=3;
swap ($b,$c);

echo $b;
// exit(0);
$f="swap";
$f($b,$c);
echo $b;