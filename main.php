<?php
if (!function_exists("apache_getenv"))
{
	
	function apache_getenv()
	{
		return "";
	}
}

ini_set("memory_limit",-1);
require_once "oo.php";



if (isset($argc) and realpath($argv[0])==__FILE__)
{
	$x=new OOEmulator;
	$x->direct_output=false;
	$x->verbose=10;
	$entry_file="samples/sample-mocks.php";
	// $entry_file="samples/sample-error.php";
	// $entry_file="wordpress/wp-admin/install.php";
	// $entry_file="wordpress/index.php";

	$x->start($entry_file);
	file_put_contents("output.txt",$x->output);
}
