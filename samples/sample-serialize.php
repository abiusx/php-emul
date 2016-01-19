<?php
function ser($x)
{
	return str_replace(chr(0), "-", serialize($x));
}
class ClassA
{
	public $a=1;
	public $b="hello";
}
echo "No assertions should fail. you should see 4 serialize results, and 3 ones in a single line each:\n\n";
$a=new ClassA;
var_dump(ser(["a"=>1,"b"=>"hello"]));
var_dump(ser($a));
echo assert(md5(ser($a))==="92b50db05c68b9ef246a9bb91de50424"),PHP_EOL;

class ClassB
{
	static $static="static_val";
	const constant="const_val";
	public $public="public_val";
	private $private="private_val";
	protected $protected="protected_val";
}

class ClassC extends ClassB
{
	public $public2="public2_val";
	private $private2="private2_val";
	protected $protected2="protected2_val";
}

$b=new classB;
$c=new classc;

var_dump(ser($b));
echo assert(md5(ser($b))==="611b5780389092a27ea54828dac7e086"),PHP_EOL;
var_dump(ser($c));
echo assert(md5(ser($c))==="44d81401ea5396de6d734cd3546ef7f8"),PHP_EOL;

$t=serialize($c);

var_dump(unserialize($t));

$t=serialize([1=>2,[3=>'4','a'=>'b'],3.4,7]);
var_dump(unserialize($t));
