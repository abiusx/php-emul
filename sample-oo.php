<?php
interface ia
{
	function qq()
	{
		return 2;
	}
}
interface ib{}
class Something
{
	const ABC="abc",DEF=0;
	protected $x;
	public $y;
	private $z="abc",$u=1;
	static public $s=0;
	function __construct()
	{
		echo 2;
	}
	function Something()
	{
		echo 1;

	}
	function f()
	{
		echo "hi";
	}
}

class SomethingElse extends Something implements ia,ib
{

}

// $x=new Something();