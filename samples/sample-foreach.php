<?php
foreach (range(0,10) as &$v)  echo $v;
foreach (range(0,10) as $v)  echo $v;
foreach (range(0,10) as $k=>$v)  echo $v;
foreach (range(0,10) as $k=>&$v)  echo $v;
echo PHP_EOL;
$args=['hello',false,true,5];
$temp = array();
foreach ($args as &$arg)
    $temp[] = &$arg;
var_dump($temp);

$temp=array();
$temp[]=&$args[0];
$temp[]=&$args[1];
$temp[]=&$args[2];
var_dump($temp);



echo str_repeat("-", 40),PHP_EOL;
$list=[10,20,30];
foreach ($list as &$v) $v++;
var_dump($list);
foreach ($list as $v) $v++;
var_dump($list);
foreach ($list as $k=>$v) $v++;
var_dump($list);
foreach ($list as $k=>&$v) $v++;
var_dump($list);

//Warning:
// Reference of a $value and the last array element remain even after the foreach loop. It is recommended to destroy it by unset(). Otherwise you will experience the following behavior:
echo str_repeat("-", 40),PHP_EOL;
$list=[10,20,30];
foreach ($list as &$v) $v++;
var_dump($list);unset($v);
foreach ($list as $v) $v++;
var_dump($list);unset($v);
foreach ($list as $k=>$v) $v++;
var_dump($list);unset($v);
foreach ($list as $k=>&$v) $v++;
var_dump($list);unset($v);