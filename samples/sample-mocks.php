<?php
echo "\n=====Testing constant functions=====\n";

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


echo "\n=====Testing output functions=====\n";

echo "Here comes BArray(3) Array(2): ";
print_r(A);
print_r([1,2,3]);
echo print_r([4,5],true),PHP_EOL;


echo "Here comes var_dump of true: ";
var_dump(true);
echo "Here comes var_dump of 3 numbers: ";
var_dump(1,2,3);


printf("Hello %s #%d\n","world","1");

vprintf("Again, hello %s #%d!\n",["world","2"]);

var_export([1,2,3]);
echo "should be the same as: ";
echo var_export([1,2,3],true)," and equal to 0=>1,1=>2,2=>3.",PHP_EOL;

echo "\n=====Testing extract/compact functions=====\n";

$x=1;
$y=2;
$z="3";
echo "Should be x=>1,y=>2 then x=>1,y=.2,z=>'3':\n";

$a=compact("x","y","Q",2.5);
var_dump($a);
$a=compact(["x","y",["z"]]);
var_dump($a);

extract(["a"=>5,"b"=>"10"]);
echo $a,"=5 & 10=",$b,PHP_EOL;

#TODO: more thorough tests for extract? it has many weird flags