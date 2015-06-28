<?php
interface ia
{
	function qq();
}
interface ib{}
class Something
{
	const ABC="abc",DEF=0;
	protected $x="protected";
	public $y="public";
	private $z="abc",$u=1;
	static public $s="01234";
	function __construct()
	{
		echo "Something::Construct",PHP_EOL;
	}
	function Something()
	{
		echo 1,PHP_EOL;

	}
	function f()
	{
		echo "Something::f",PHP_EOL;
	}
	static function sta()
	{
		echo "Something::sta 01234=",self::$s,PHP_EOL;
	}
}

abstract class SomethingElse extends Something implements ia,ib
{

}
class SomethingDeep extends Something
{
	public $x="override"; //simply overwrites $x from parent.
	function __construct()
	{
		echo "SomethingDeep::construct",PHP_EOL;
	}
}
echo "01234=",Something::$s,PHP_EOL;
echo "Something::Construct=";
$x=new Something();
$temp="y";
echo "public=",$x->$temp,PHP_EOL;
echo "Something::f=";
$x->f();
$x::sta();
Something::sta();
Something::$s="hello";

echo "hello=",SomethingElse::$s,PHP_EOL;
echo "SomethingDeep::construct=";
$y=new SomethingDeep();
echo "Something::f=";
$y->f();
echo "override=",$y->x,PHP_EOL;
echo "public=",$x->y,PHP_EOL;
$x->y++;
echo "publid=",$x->y,PHP_EOL;

echo 'Testing late static binding...',PHP_EOL;
class Parent_
{
	static $static_var=1;
	function what()
	{
		echo self::$static_var,"=1",PHP_EOL;
		echo static::$static_var,"=2",PHP_EOL;
	}
	static function swhat()
	{
		echo self::$static_var,"=1",PHP_EOL;
		echo static::$static_var,"=2",PHP_EOL;
	}
}
class Child extends Parent_
{
	static $static_var=2;
}
$child=new Child();
$child->what();
Child::swhat();
#TODO: late static binding sample, parent sample