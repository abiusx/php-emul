<?php
interface ia
{
	function qq();
}
interface ib{}
class Something
{
	const ABC="abc",DEF=0;
	protected $x;
	public $y;
	private $z="abc",$u=1;
	static public $s="01234";
	function __construct()
	{
		echo 2,PHP_EOL;
	}
	function Something()
	{
		echo 1,PHP_EOL;

	}
	function f()
	{
		echo "hi",PHP_EOL;
	}
	static function sta()
	{
		echo self::$s,PHP_EOL;
	}
}

abstract class SomethingElse extends Something implements ia,ib
{

}
class SomethingDeep extends Something
{
	function z__construct()
	{
		echo 3,PHP_EOL;
	}
}
echo Something::$s,PHP_EOL;
$x=new Something();

$x->f();
$x::sta();
Something::sta();

echo SomethingElse::$s,PHP_EOL;
$y=new SomethingDeep();
$y->f();
