<?php
function autoloader1($class)
{
	echo __FUNCTION__." called\n";
	if ($class!=="B" and $class!=="C")
	eval("class {$class} {}");
}
function autoloader2($class)
{
	echo __FUNCTION__." called\n";
	if ($class!=="C")
	eval("class {$class} {}");
}

echo "Should give you: f1tt\n";
var_dump(class_exists("A"));
spl_autoload_register("autoloader1");
var_dump(class_exists("A"));
spl_autoload_register("autoloader2");
var_dump(class_exists("A"));

echo "\nShould give you: 12t12f\n";
var_dump(class_exists("B"));
var_dump(class_exists("C"));

spl_autoload_unregister("autoloader");
echo "\nShould give you: 2f2t\n";
spl_autoload_unregister("autoloader1");
var_dump(class_exists("C"));
spl_autoload_call("D");
var_dump(class_exists("D",false));
