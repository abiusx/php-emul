#!/usr/bin/env php
<?php
if (count($argv)<=1)
	die("Usage: test.php tests/*.php\n");
echo "This tool checks whether a PHP script runned by the emulator matches the same script runned by native PHP.",PHP_EOL;
// require_once "main.php";

array_shift($argv); //test.php
if ($argc>1 and $argv[0]=='--diff')
{
	$diff=true;
	array_shift($argv); 
}
do_test($argv);


function do_test($files)
{
	foreach ($files as $file)
	{
		global $diff;

		if (is_dir($file))
		{

			$str=" Directory {$file} ";
			$x=80-strlen($str);
			echo str_repeat("-", $x/2),$str,str_repeat("-",$x/2),PHP_EOL;
			do_test($file);
		}
		$x=80-strlen($file)-4;
		
		if ($diff) echo str_repeat("-",$x/2), " ";
		echo $file;
		fflush(STDOUT);
		$res1=shell_exec("php '{$file}' 2>&1");
		shell_exec("php main.php -f '{$file}'  -o output.txt 2>&1");
		$res2=file_get_contents("output.txt");
		if ($res1===$res2)
		{

			echo " \033[32m ",'✓ ',"\033[0m";
			if ($diff) echo str_repeat("-",$x/2),PHP_EOL;
		}
		else
		{
			echo " \e[31m X ","\033[0m";
			if ($diff)
			{
				echo str_repeat("-",$x/2),PHP_EOL;
				file_put_contents("1.txt", $res1);
				file_put_contents("2.txt", $res2);
				echo shell_exec("diff --text 1.txt 2.txt");
			}
		}
		if (!$diff) echo PHP_EOL;
		fflush(STDOUT);
	}

}

@unlink("1.txt");
@unlink("2.txt");