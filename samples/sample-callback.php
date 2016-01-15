<?php
function plusone(&$x)
{
  return ++$x;
}
echo "Should start from 6 and go to 15: ";
$a=[5,6,7,8,9];
var_dump(array_map("plusone", $a));
$a=[10,11,12,13,14];
echo "true=";
var_dump(array_walk($a,"plusone"));
var_dump($a);

die();


$fruits = array("d" => "lemon", "a" => "orange", "b" => "banana", "c" => "apple");

function test_alter(&$item1, $key, $prefix)
{
    $item1 = "$prefix: $item1";
}

function test_print($item2, $key)
{
    echo "$key. $item2\n";
}

echo "Before ...:\n";
array_walk($fruits, 'test_print');

array_walk($fruits, 'test_alter', 'fruit');

echo "... and after:\n";
array_walk($fruits, 'test_print');

// die();
#TODO: work with closures, emulator should support them too. Its php 5.3+
function basic_callback($n)
{
	return $n*$n;
}
function double_callback($x,$y)
{
	return $x*$y;
}
$a=[1,2,3];
$b=[2,4,8];
var_dump(array_map("basic_callback",$a));
var_dump(array_map("double_callback",$a,$b));



$text = "April fools day is 04/01/2002\n";
$text.= "Last christmas was 12/24/2001\n\n";
// the callback function
function next_year($matches)
{
  // as usual: $matches[0] is the complete match
  // $matches[1] the match for the first subpattern
  // enclosed in '(...)' and so on
  return $matches[1].($matches[2]+1);
}
echo preg_replace_callback(
            "|(\d{2}/\d{2}/)(\d{4})|",
            "next_year",
            $text);


