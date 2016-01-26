<?php
function trait_exists_mock($emul,$name,$autoload=true)
{
	if ($autoload) $emul->autoload($name);
	return trait_exists($name,$autoload) or $emul->user_trait_exists($name);
}