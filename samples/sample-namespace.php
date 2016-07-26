<?php
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


