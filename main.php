<?php
//function available in apache
if (!function_exists("apache_getenv"))
{
	function apache_getenv()
	{
		return "";
	}
}



$usage="Usage: php main.php -f file.php [-v verbosity --output --strict]\n";
if (isset($argc))// and realpath($argv[0])==__FILE__)
{
	$options=getopt("f:v:o",['strict','output']);
	if (!isset($options['f']))
		die($usage);
	
	ini_set("memory_limit",-1);
	require_once "oo.php";
	$x=new OOEmulator;
	$x->strict=isset($options['strict']);
	$x->direct_output=isset($options['output']);
	if (isset($options['v'])) $x->verbose=$options['v'];
	$entry_file=$options['f'];

	$x->start($entry_file);
	if (!isset($options['output'])) 
		file_put_contents("output.txt",$x->output);

}
else
	die($usage);