<?php
abstract class AB {};
class CL {};
interface IN {};
trait TR {};

echo "Should be tff, tff, ftf, fft\n";
foreach (["AB","CL","IN","TR"] as $x)
{
	$alias="AL_".$x;
	class_alias($x,$alias);

var_dump(class_exists($x));
var_dump(interface_exists($x));
var_dump(trait_exists($x));
echo PHP_EOL;
}

echo str_repeat("-",80),PHP_EOL;
echo "Same pattern, for aliases:\n";
foreach (["AB","CL","IN","TR"] as $x)
{
	$x="AL_".$x;
var_dump(class_exists($x));
var_dump(interface_exists($x));
var_dump(trait_exists($x));
echo PHP_EOL;
}

var_dump(array_slice(get_declared_classes(),-4));
var_dump(array_slice(get_declared_interfaces(),-2));
var_dump(array_slice(get_declared_traits(),-2));