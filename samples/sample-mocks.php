<?php
#constants
define ("a","b");
const c='d';

echo get_defined_constants()['a'],"=b\n";
echo get_defined_constants()['c'],"=d\n";
echo c,"=d\n";
echo a,"=b\n";

const C="D";
define("A","B",false);
echo C,"=D\n";
echo A,"=B\n";

echo defined("A"),defined("a"),defined("C"),defined("c"),"=1111",PHP_EOL;