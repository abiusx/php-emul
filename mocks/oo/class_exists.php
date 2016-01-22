<?php

function class_exists_mock(OOEmulator $emul,$name,$autoload=true)
{
	return $emul->class_exists($name);
}
