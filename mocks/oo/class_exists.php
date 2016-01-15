<?php

function class_exists_mock(OOEmulator $emul,$name)
{
	return class_exists($name) or array_key_exists(strtolower($name),$emul->classes);
}
