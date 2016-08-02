#!/usr/bin/env php
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
	// $_SERVER['REQUEST_URI']="/{$options['f']}";
	ini_set("memory_limit",-1);
	require_once "oo.php";
	$emul=new OOEmulator(); 
	$emul->strict=isset($options['strict']);
	$emul->direct_output=isset($options['output']);
	if (isset($options['v'])) $emul->verbose=$options['v'];
	
	$emul->start($options['f']);
	if (!isset($options['output'])) 
		file_put_contents("output.txt",$emul->output);

	if (isset($emul->termination_value))
		exit($emul->termination_value);
	else
		exit(0);
}
else
	die($usage);

