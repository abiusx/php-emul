<?php

function basic_callback($n)
{
	return $n*$n;
}
function double_callback($x,$y)
{
	return $x*$y;
}
$a=[1,2,3];
$b=[2,4,8];
var_dump(array_map("basic_callback",$a));
var_dump(array_map("double_callback",$a,$b));
