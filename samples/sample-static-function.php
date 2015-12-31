<?php


function static_func()
{
	static $a=0;
	static $b;
	static $c,$d=1;
	echo $a++,PHP_EOL;
}
for ($i=0;$i<10;++$i)
	static_func();