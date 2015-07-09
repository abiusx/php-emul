<?php
// assign & assign ref
$a= 1;

$b = &$a;
$b++;
assert($a == 2);

$c = &$b;
$c++;
assert($a == $b && $b == $c && $c == 3);

// -------------------------- //
$info = array('coffee', 'brown', 'caffeine');
list($drink, $color, $power) = $info;
assert($drink == 'coffee' && $color == 'brown' && $power == 'caffeine');

list($drink1, , $power1) = $info;
assert($drink1 == 'coffee' && $power1 == 'caffeine');

list($aa, list($bb, $cc)) = array(1, array(2, 3));
assert($aa == 1 && $bb == 2 && $cc == 3);

$info = array('coffee', 'brown', 'caffeine');
list($aaa[1], $aaa[0], $aaa[2]) = $info;
assert($aaa == array('brown', 'coffee', 'caffeine'));

$string = "abcde";
list($foo) = $string;
assert($foo == "a"); # http://php.net/manual/en/function.list.php#110563

list($aaaa, $bbbb, $cccc) = array("a", "b", "c", "d");
assert($aaaa == 'a' && $bbbb == 'b' && $cccc === 'c');

$parameter = 'name';
list( $P, $G ) = array_merge( explode( '=', $parameter ), array( true ) );
assert($P == 'name' && $G == true);		# $G == true is to make sure Emulator works exactly as PHP