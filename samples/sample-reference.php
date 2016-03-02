<?php
echo "direct reference\n";
$x=5;
$z=&$x;
$z++;
var_dump($x);

$str="abc";

echo "str reference and array element reference\n";
$str_ref_arr=array(&$str,2,3);
var_dump($str_ref_arr);
$just_ref=&$str;


$str_ref_arr[0].="xyz";
var_dump($str_ref_arr);
var_dump($str);

$just_ref.="hello";

var_dump($just_ref);
var_dump($str);

echo "array ref in function\n";
function f(&$x,$a)
{
	// var_dump(func_get_args());
	var_dump($a);
	$a[0].=" nana";
	$x++;
	// var_dump(func_get_args());
}

f($z,$str_ref_arr);
var_dump($x);
var_dump($str);

echo "call_user_func\n";

call_user_func_array("f", array(&$z,$str_ref_arr));
var_dump($x);
var_dump($str);
// exit(0);

echo "fetch through trace\n";

function f2()
{
	$a=func_get_args();
	var_dump($a);
	$a[1][0].=" final.";
	$a[0]++;
}

f2($z,$str_ref_arr);
var_dump($x);
var_dump($str);

echo "call_user_func fetch through trace\n";
call_user_func_array("f2", array(&$z,$str_ref_arr));
var_dump($x);
var_dump($str);
exit(0);

echo "function rerefence args\n";
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