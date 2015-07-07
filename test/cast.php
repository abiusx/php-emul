<?php
$a = '1';
assert((int)$a === 1);

$a = '1';
$a += 1;
assert($a === 2);

$a += 1.2;
assert($a === 3.2);

$a = 5 + " good men";	# string value is 0 and + operator sums the integer values
assert($a === 5);

$a .= " good men";
assert($a === "5 good men");

$object = new StdClass;
$object->foo = 1;
$object->bar = 2;
$casted = (array)$object;
assert( $casted['foo'] === 1 &&
		$casted['bar'] === 2
		);

$n = 2;
assert( (double)$n === (double)2 &&
		(double)$n === 2.00
		);
		
$a = 2;
assert((bool)$a === true);

$a = 0;
assert((bool)$a === false);

$a = 59;
assert((string)$a === '59');

assert((binary) 'binary string' === b"binary string");

$a= (object) array(  'mamad' => 'jafar',
			'hi' => 1 );
$a->{1} = 'a';
assert($a -> mamad === 'jafar' && $a -> hi === 1 && $a -> {'1'} === 'a');