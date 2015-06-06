<?php
$s="echo 'hi';";
eval($s);


$a=1;
$b=&$a;
$b=5;

echo $a;