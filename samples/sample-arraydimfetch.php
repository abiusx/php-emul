<?php
//null test
$a=[];
$a[]=4;
$a[null]=5;
$a[]=6;
$a[]=7;
var_dump($a);
die();
