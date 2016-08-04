<?php
$x=1;
register_shutdown_function('f');
// $x->method();  //error
$y=3<<-1; //ArithmeticError
try {
    $x->method(); 
    $y=3<<-1;
}
catch (Error $x)
{
	echo "Nice!";
}


function f()
{
	echo "Shutdown...\n";
}