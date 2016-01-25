<?php
if (!function_exists("apache_getenv"))
{
	function apache_getenv()
	{
		return "";
	}
}



$usage="Usage: php main.php -f file.php [-v verbosity -o]\n";
if (isset($argc) and realpath($argv[0])==__FILE__)
{
	$options=getopt("f:v:o");
	if (!isset($options['f']))
		die($usage);
	
	ini_set("memory_limit",-1);
	require_once "oo.php";
	$x=new OOEmulator;
	$x->direct_output=isset($options['o']);
	if (isset($options['v'])) $x->verbose=$options['v'];
	$entry_file=$options['f'];
	// $entry_file="samples/sample-forward-static.php";
	// $entry_file="wordpress/wp-admin/install.php";
	// $entry_file="wordpress/index.php";

	$x->start($entry_file);
	if (!isset($options['o'])) 
		file_put_contents("output.txt",$x->output);
}
else
	die($usage);