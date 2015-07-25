<?php
@$p = ++$s;
// should not print error

$a = array();
@$a[mamad] = salar;		// #bug
assert($a['mamad'] == 'salar');
// should not print error, currently it does

@$b['mam'] = 'ad';
assert($b['mam'] == 'ad');
// kollan should not print error, hamintori for fun :D

@$j = eval('63 / 0;');
assert($j === NULL);
@$p = eval('63 - 3 + 2 / 1'); // (No semicolon)
assert($p === FALSE);