<?php
function get_class_vars_mock($emul,$classname)
{
	if (class_exists($classname)) return get_class_vars($classname);

	$out=[];
	$class=$emul->classes[strtolower($classname)];
	foreach ($class->properties as $k=>$v)
		$out[$k]=$v;
	foreach ($class->static as $k=>$v)
		$out[$k]=$v;
	return $out;
}