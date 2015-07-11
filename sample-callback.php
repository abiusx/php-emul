<?php

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
$text.= "Last christmas was 12/24/2001\n";
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

