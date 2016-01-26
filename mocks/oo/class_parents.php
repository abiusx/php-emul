<?php
function class_parents_mock($emul,$class_or_object,$autoload=true)
{
	if ($autoload) $emul->autoload($name);
	$class=$class_or_object;
	if (!is_string($class))
		$class=$emul->get_class($class_or_object);
	if (class_exists($class) and $class!="EmulatorObject" ) return class_parents($class_or_object,$autoload);
	$parents=array_slice($emul->ancestry($class),1);
	return array_combine($parents,$parents);
}