<?php
// $a++;
// $a->zzz[0];
$x=[0=>false];
foreach ($x as $t)
{
	var_dump(empty($x[0]));
	var_dump(empty($x->abc));
	var_dump(empty($x[0][1][2]->abc));
	var_dump(isset($x[0]));
	var_dump(isset($x->abc));
	var_dump(isset($x[0][1][2]->abc));
}
