<?php
class something{
	private $some;
	public $other;
	protected $variable;
	function sayHello($to){
		echo "Hello $to.";
	}
	function retFive(){
		return 5;
	}
}

$some = new soMeThInG();
assert($some -> retFive() === 5);
assert($some instanceof something);
assert($some instanceof SomEThiNg);	// case sensitiveness :D