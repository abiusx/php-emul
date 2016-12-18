<?php
function get_class_methods_mock($emul,$class_or_object)
{
	$class=$class_or_object;
	if (!is_string($class))
		$class=$emul->get_class($class);

	if (class_exists($class) and $class!="EmulatorObject") return get_class_methods($class_or_object);
	if (!$emul->user_class_exists($class)) return [];
	$class=$emul->classes[strtolower($class)];
	foreach ($class->methods as $k=>$v)
		$out[]=$v->name;
	return $out;
}