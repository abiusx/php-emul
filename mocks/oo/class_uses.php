<?php
function class_uses_mock($emul,$class_or_object,$autoload=true)
{
	if ($autoload) $emul->autoload($name);
	$class=$class_or_object;
	if (!is_string($class))
		$class=$emul->get_class($class_or_object);
	if (class_exists($class) and $class!="EmulatorObject" ) return class_implements($class_or_object,$autoload);
	$traits=$emul->classes[strtolower($class)]->traits;
	return array_combine($traits,$traits);
}