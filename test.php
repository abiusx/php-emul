<?php

echo "This tool checks whether a PHP script runned by the emulator matches the same script runned by native PHP.",PHP_EOL;
require_once "main.php";

$file=$options['f'];
$res=shell_exec("php '{$file}'");

echo str_repeat("-", 80),PHP_EOL;
if ($res===$x->output)
	echo "Compatible!",PHP_EOL;
else
{
	echo "Incompatible:",PHP_EOL;
	file_put_contents("actual.txt", $res);
	echo shell_exec("diff --text actual.txt output.txt");
}
