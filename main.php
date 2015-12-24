<?php

require_once "emulator.php";








// $_GET['url']='http://abiusx.com/blog/wp-content/themes/nano2/images/banner.jpg';

if (isset($argc) and realpath($argv[0])==__FILE__)
{
	$x=new Emulator;
	$entry_file="sample-stmts.php";
	// $entry_file="wordpress/index.php";

	$x->start($entry_file);
	
	// $x->start("sample-isset-empty.php");
	// echo(($x->output));
}
// $x->start("yapig-0.95b/index.php");
// echo "Output of size ".strlen($x->output)." was generated.",PHP_EOL;
// var_dump(substr($x->output,-100));
// echo PHP_EOL,"### Variables ###",PHP_EOL;
// var_dump($x->variables);