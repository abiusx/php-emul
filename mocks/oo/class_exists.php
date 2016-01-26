<?php

function class_exists_mock(OOEmulator $emul,$name,$autoload=true)
{
	$emul->autoload($name);
	return $emul->class_exists($name);
}
