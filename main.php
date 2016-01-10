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



// $_GET['url']='http://abiusx.com/blog/wp-content/themes/nano2/images/banner.jpg';

if (isset($argc) and realpath($argv[0])==__FILE__)
{
	$x=new OOEmulator;
	$x->direct_output=false;
	$x->verbose=5;
	$entry_file="samples/sample-function-advanced.php";
	// $entry_file="wordpress/index.php";
	$entry_file="wordpress/wp-admin/install.php";

	$x->start($entry_file);

	file_put_contents("output.txt",$x->output);
// $x->start("wordpress/index.php");
// $x->start("wordpress/wp-admin/install.php");
// $x->start("sample-oo.php");
// echo "Output of size ".strlen($x->output)." was generated:",PHP_EOL;
// var_dump(substr($x->output,-200));
// echo(($x->output));
	// $x->start("sample-isset-empty.php");
	// echo(($x->output));
}
// $x->start("yapig-0.95b/index.php");
// echo "Output of size ".strlen($x->output)." was generated.",PHP_EOL;
// var_dump(substr($x->output,-100));
// echo PHP_EOL,"### Variables ###",PHP_EOL;
// var_dump($x->variables);