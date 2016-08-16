<?php
function class_implements_mock($emul,$class_or_object,$autoload=true)
{
	$class=$class_or_object;
	if ($autoload) $emul->autoload($class);
	if (!is_string($class))
		$class=$emul->get_class($class_or_object);
	if (class_exists($class) and $class!="EmulatorObject" ) return class_implements($class_or_object,$autoload);
	$interfaces=$emul->classes[strtolower($class)]->interfaces;
	return array_combine($interfaces,$interfaces);
}