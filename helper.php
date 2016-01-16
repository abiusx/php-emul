<?php
#TODO: capture writes to STDOUT as output
function write_functions($file)
{
	$functions=get_defined_functions();
	$o=[];
	foreach ($functions['internal'] as $f)
		$o[]=str_pad($f, 30," ");

	file_put_contents($file, implode("\n",$o));
}

function get_emulation_functions($file)
{
	$functions=explode("\n",file_get_contents($file));
	$o=[];
	foreach ($functions as $f)
	{
		$t=explode(" ",$f);
		$t=array_filter($t);
		$t=array_values($t);
		if (count($t)>1)
			for ($i=1;$i<count($t);++$i)
				$o[$t[$i]][]=$t[0];
	}
	return $o;
}
function get_callback_functions()
{
	$out=[];
	foreach (get_defined_functions()['internal'] as $f)
	{
		$r=new ReflectionFunction($f);
		$p=$r->getParameters();
		$flag=false;
		foreach ($p as $param)
			if (strpos($param->getName(),"callback")!==false or strpos($param->getName(),"funcname")!==false) 
				{
					if (!$flag) $out[$f]=[];
					$out[$f][$param->getPosition()]=($param->getName());
					$flag=true;
				}
	}
	return $out;
}

function check_mock_progress($functions_file)
{
	$funcs=get_emulation_functions($functions_file);

	foreach (glob("mocks/*.php") as $inc)
		require_once $inc;
	foreach (glob("mocks/oo/*.php") as $inc)
		require_once $inc;

	$count=0;$done=0;
	foreach ($funcs['emul'] as $f)
	if (!function_exists($f."_mock"))
		echo str_pad(++$count,3),") ",str_pad($f."()",30)," is emulation sensitive and lacks mocking.",PHP_EOL;
	else
		$done++;

	echo "Result: A total of {$done} functions out of ".count($funcs['emul'])." required for emulation are mocked.",PHP_EOL;	
}
$functions_file="functions.def.txt";

check_mock_progress($functions_file);
// var_export(get_callback_functions());