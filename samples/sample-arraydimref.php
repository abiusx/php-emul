<?php
$int=5;
$bool=true;
$null=null;
$float=5.1;

$index_v=5;
$index_r=6;


echo "Null:\n";
$null_v=$null[$index_v];
$null_r=&$null[$index_r]; //$null is converted into array and referenced to $null_r, which evaluates to null
var_dump($null,$null_v,$null_r);
echo str_repeat("-",80),PHP_EOL;

// die();
echo "Int:\n";
$int_v=$int[$index_v];
$int_r=&$int[$index_r];
var_dump($int,$int_v,$int_r);
echo str_repeat("-",80),PHP_EOL;

echo "Bool:\n";
$bool_v=$bool[$index_v];
$bool_r=&$bool[$index_r];
var_dump($bool,$bool_v,$bool_r);
echo str_repeat("-",80),PHP_EOL;



echo "Float:\n";
$float_v=$float[$index_v];
$float_r=&$float[$index_r];
var_dump($float,$float_v,$float_r);
echo str_repeat("-",80),PHP_EOL;


echo "Null multidim:\n";
$null2=null;
$null2_v=$null2[1][2][3];
$null2_r=&$null2[1][2][3];
var_dump($null2,$null2_v,$null2_r);


die();
$arr=null;

$x=$arr['something'];
var_dump($x,$arr);

$x=&$arr['something'];

var_dump($x,$arr);


echo str_repeat("-",80);




class Something {
	public $arr;
	public static $sarr;

}

$x=new Something;


$t=&$x->arr['yoyo'];
$t2=Something::$sarr['yoyo']; //notice
$yoyo='yoyo2';
$t2=&Something::$sarr[$yoyo];

var_dump($x);
var_dump(Something::$sarr);
