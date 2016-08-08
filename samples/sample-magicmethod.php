<?php

class MyClass
{
	public $prop=5;
	private $arr=['hello'=>'world'];
	public function f()
	{
		return 5;
	}
	public static function fs()
	{
		return 6;
	}
	function __call($name,$args)
	{
		echo "$name called with ".count($args)." args.\n";
		return "OK";
	}

	function __get($name)
	{
		echo "Get $name\n";
		return $this->arr[$name];
	}
	function __set($name,$value)
	{
		echo "Set $name\n";
		$this->arr[$name]=$value;

	}
	static function __callStatic($name,$args)
	{
		echo "$name called statically with ".count($args)." args\n ";
		return "OKs";
	}

	function __isset($name)
	{
		echo "Isset $name\n";
		return isset($this->arr[$name]);
	}
	function __unset($name)
	{
		echo "Unset $name\n";
		unset($this->arr[$name]);
	}
}


$x=new MyClass;

var_dump($x->f());
var_dump($x::fs());
var_dump(MyClass::fs());
var_dump($x->prop);


$x->test=999;

var_dump($x->test);
var_dump($x->hello);
var_dump(empty($x->hello2));

var_dump($x->nonexistent(1,2,3,4,5));
var_dump($x::hello(9,8,7));

var_dump(isset($x->hi));
var_dump(isset($x->test));
unset($x->test);
var_dump(isset($x->test));

var_dump($x->nop); //notice

echo str_repeat("-",80),PHP_EOL;
class MyClass2 extends MyClass {}
$x=new MyClass2;

var_dump($x->f());
var_dump($x::fs());
var_dump(MyClass::fs());
var_dump($x->prop);


$x->test=999;

var_dump($x->test);
var_dump($x->hello);
var_dump($x->nonexistent(1,2,3,4,5));
var_dump($x::hello(9,8,7));

var_dump(isset($x->hi));
var_dump(isset($x->test));
unset($x->test);
var_dump(isset($x->test));

var_dump($x->nop); //notice

echo str_repeat("-",80),PHP_EOL;

//magic method recursion test

class MagicRecurser {
	public $a=5,$b=6;
	function __isset($name)
	{
		echo "ISSET\n";
		return isset($this->{$name});
	}
	function __get($name)
	{
		echo "GET\n";
		if (isset($this->{$name}))
			return $this->{$name};
	}
}
$x=new MagicRecurser;
var_dump(isset($x->a));
var_dump(($x->c));
var_dump(isset($x->c));
var_dump(empty($x->c));