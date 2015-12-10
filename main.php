<?php

require_once "emulator.php";

//this loads all functions, so that auto-mock will replace them
foreach (glob(__DIR__."/mocks/*.php") as $mock)
	require_once $mock;











// $_GET['url']='http://abiusx.com/blog/wp-content/themes/nano2/images/banner.jpg';
if (isset($argc) and $argv[0]==__FILE__)
{
	$x=new Emulator;
	$x->start("sample-stmts.php");
	// $x->start("sample-isset-empty.php");
	// echo(($x->output));
}
// $x->start("yapig-0.95b/index.php");
// echo "Output of size ".strlen($x->output)." was generated.",PHP_EOL;
// var_dump(substr($x->output,-100));
// echo PHP_EOL,"### Variables ###",PHP_EOL;
// var_dump($x->variables);