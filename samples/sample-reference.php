<?php
$a=$b=5;
function z(&$a,&$b)
{
	$a++;
	$b++;
}



z($a,$b);
$b+=5;
echo $a,"=6 & 11=",$b,PHP_EOL;


exec("ls",$output,$return);
echo "a number >10,0:";
var_dump(count($output));
var_dump($return);