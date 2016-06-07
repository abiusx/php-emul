<?php

class A
{
	public $val=0;
	function f($x)
	{
		echo "A::f() is ",$x*$this->val,PHP_EOL;
	}
	static function g()
	{
		echo $this->val,PHP_EOL;
	}
}

class B extends A
{

	function f($x)
	{
		parent::f($x*2);
		echo "B::f() is ",$x*$this->val,PHP_EOL;
		A::f($x*3);
	}
}
 
class C extends B
{

	function f($x)
	{
		parent::f($x*2);
		B::f($x*2);

		echo "C::f() is ",$x*$this->val,PHP_EOL;
		A::f($x*5);
		// A::g();
	}

}

$c=new C;
$c->val=1;
$c->f(1);