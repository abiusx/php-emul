<?php
$a = true;
$b = false;
assert(!$a === $b);
assert(!$b === true);

$c = 2;					// 0000 0010
assert(~$c === -3);		// 1111 1101

$d = -5;
assert(-$d === +5);
assert(+$d === -5); 

$e = 5;
assert(++$e === 6);
assert($e++ === 6);
assert(--$e === 6);
assert($e-- === 6);
assert($e   === 5);