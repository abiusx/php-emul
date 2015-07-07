<?php
function in_file_func($a, &$b){
	echo $a;
	$b++;
}

$aa = 5;
$bb = 6;
in_file_func($aa, $bb);		//user function
assert($bb == 7);

$a= strrev('abc');		// core function call
assert($a == 'cba');

// #TODO: mocked function call