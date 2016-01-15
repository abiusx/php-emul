<?php
function compact_mock($emul)
{
	$args=func_get_args();
	array_shift($args);
	$var_names=[];

	foreach ($args as $arg)
	{
		if (is_array($arg))
			foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator($arg)) as $name)
				$var_names[]=$name;
		else
			$var_names[]=$arg;
	}

	$out=[];
	foreach ($var_names as $var_name)
		if (isset($emul->variables[$var_name]))
			$out[$var_name]=$emul->variables[$var_name];
	return $out;
}