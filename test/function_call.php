<?php
function randomiz(){
	return 4;	// guarrantied
}

assert(raNDoMiz() === 4);	// php treats case insensitive with funcs
							// #bug

assert(Md5("1") == 'c4ca4238a0b923820dcc509a6f75849b');
							
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