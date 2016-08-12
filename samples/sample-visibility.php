<?php

class Something {
	private $x=2;
	function __get($name)
	{
		if ($name=="x") return 3;
	}
}
$x=new Something;
var_dump($x->x); //should be 3


class A
{
	public $A_public=1;
	protected $A_protected=2;
	private $A_private=3;
	function A_go()
	{
		echo $this->A_public,$this->A_protected,$this->A_private;
	}
}

class b extends A
{
	public $B_public=4;
	protected $B_protected=5;
	private $B_private=6;

	function B_go()
	{
		echo $this->A_public,$this->A_protected;
		echo PHP_EOL;
		echo $this->A_private; //notice
		echo $this->B_public,$this->B_protected,$this->B_private;
	}
}

$x=new B;
$x->A_go();
$x->B_go();
echo $x->A_public;
// echo $x->A_protected; //error
echo $x->A_private; //notice


echo str_repeat("-",80),PHP_EOL;


class S1
{
	public static $public=9;
	protected static $protected=8;
	private static $private=7;

	static function f()
	{
		echo self::$public,self::$protected,self::$private,PHP_EOL;
		echo static::$public,static::$protected;
		echo static::$private; //error on S2
		echo PHP_EOL; 
	}
}
class S2 extends S1 {

}
echo S2::$public;
// echo S2::$protected; //error
// echo S2::$private; //error
s1::f();
S2::f();