<?php
function in_file_func($a, &$b){
	echo $a;
	$b++;
}

$a= strrev('abc');		// core function call
assert($a == 'cba');
$aa = 5;
$bb = 6;
in_file_func($aa, $bb);
assert($bb == 7);