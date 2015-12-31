<?php

echo "Isset and Empty test:",PHP_EOL;
echo "There should be a notice about p on line 13",PHP_EOL;
echo "Results should be tf\tff\tft\ttftf\ttftt\ttfft",PHP_EOL;

$n=1;
var_dump(isset($n));
unset($n);
var_dump(isset($n));

echo PHP_EOL;

$u=$p;
var_dump(isset($u));
var_dump(isset($p));
echo PHP_EOL;

var_dump(isset($a[1][2][3]));
var_dump(empty($a[1][2][3]));
echo PHP_EOL;

var_dump(empty($x));
var_dump(isset($x));
$x=1;
var_dump(isset($x));
var_dump(empty($x));
unset($x);

echo PHP_EOL;

var_dump(empty($x));
var_dump(isset($x));
$x=false;
var_dump(isset($x));
var_dump(empty($x));
unset($x);

echo PHP_EOL;

var_dump(empty($x));
var_dump(isset($x));
$x=null;
var_dump(isset($x));
var_dump(empty($x));
unset($x);

