<?php
function class_alias_mock($emul,$original,$alias,$autoload=true)
{
	if ($autoload) $emul->autoload($original);
	if (class_exists($original))
			return class_alias($original,$alias,$autoload);
	if ($emul->user_classlike_exists($alias))
		$emul->warning("Cannot declare class {$alias}, because the name is already in use");
	elseif (!$emul->user_classlike_exists($original))
		$emul->warning("Class '{$original}' not found");
	else
		$emul->classes[strtolower($alias)]=&$emul->classes[strtolower($original)];
}