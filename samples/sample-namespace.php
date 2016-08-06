<?php
// echo "basic constant test",PHP_EOL;
namespace X1\X2 {
	const x=5;
	eval("const y=999;"); //this is not inside the namespace scope
}
namespace JESUS {

echo \X1\X2\x;
// var_dump(\X1\X2\y); //error
var_dump(y); 
}


namespace {
	function f_root() {return 2;}
	define("root",7);
	// var_dump(X1\constX1); //error, constants are not declared early
	var_dump(X1\X2\fx());
}
namespace X1\X2 {
	function fx() {return 1;}
	class Something {public $x=333;}
	const dix=12324;
}
namespace X1 {
	const constX1=9;
	var_dump(constX1);
}
namespace X1\X2\X3\X4\X5\X6\X7\X8\X9\X10\X11\X12\X13\X14\X15\X16\X17\X18\X19
{
	var_dump(\X1\constX1);
	use X1 as Z;
	use X1\X2\X3, X1\X2\X3\X4;
	use X1\X2\something;
	use X1\X2\dix;
	// use const X1\X2\dix; //not supported yet
	use X1\X2\fx;
	// var_dump(dix); //error, but not in emulator (should be use const)
	// var_dump(fx()); //error, but not in emulator
	$t=new something;
	var_dump($t->x);
	var_dump(get_class($t));
	// use X1\X2\X3\x4\x5 as z; //error
	$c=0;
	for ($i=0;$i<10;++$i)
		$c+=Z\X2\fx();
	var_dump($c);
	var_dump(Z\constX1);
	var_dump(constant("root"));
	var_dump(root);
	var_dump(f_root());
	$x=new \Exception();
}
namespace {
	use X1\X2;
	var_dump(x2\fx());
	// use function X1\X2\fx; //not supported yet.
	// var_dump(fx());
	die();
}
//3 modes of namespace use: unqualified, qualified and fully qualified. only the first one falls back to global scope
//and that fall back does not apply to classes
namespace {
	$a=5;
	class root {}

}

namespace NS 
{
	$a=3;
	$y=new NS2\X;
	var_dump($y);
	NS2\f();
}

namespace NS\NS2
{
	echo $a;

	$x=5;
	${"hello_{$x}"}=2; //regular parts name
	var_dump($hello_5);
	class X {};
	function f()
	{
		echo __NAMESPACE__."\\f() called.",PHP_EOL;
	}
	$r=new \root;
	// $r=new root; //error
}


namespace OUT {
	$z=new \NS\NS2\X;
	var_dump($z);
	\NS\NS2\f();
}


namespace {
	use \NS\NS2 as GG;

	GG\f();
}

/**/