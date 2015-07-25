<?php
namespace mam_ad;

$s = <<<EOD
Example of string
spanning multiple lines
using heredoc syntax.
EOD;
assert(is_scalar($s));
$s = "Test :|";	# String
assert(is_scalar($s));
$d = 5;		# LNumber
assert(is_scalar($d));
$l = 5.2;	# DNumber
assert(is_scalar($l));
$j = "mamad";
$e = "this is an encapsed string with $j";
assert(is_scalar($e));
assert("$e" == "this is an encapsed string with mamad");


# -------- Magic Consts --------
assert(is_scalar(__FILE__));
assert(is_scalar(__DIR__));
assert(is_scalar(__LINE__));
assert(is_scalar(__FUNCTION__));
assert(is_scalar(__CLASS__));
assert(is_scalar(__METHOD__));
assert(is_scalar(__NAMESPACE__));
assert(is_scalar(__TRAIT__));
# -> Although these are all unset, (func, class, method and trait), but php asserts equal, while the emulator does not, and throws exception.

assert(__LINE__ == 33);			// Use an enter key and watch the sky falling on your head

function mamad(){
	assert(__FUNCTION__ == __NAMESPACE__ . '\mamad');
}
mamad();

Trait Mammad{
	public function asse_rt() {
        assert(__TRAIT__ == __NAMESPACE__ . '\Mammad');
    }
}

Class Mamad {
	use Mammad;
	public function ass_ert(){
		assert(__CLASS__ == __NAMESPACE__ . '\Mamad');
		assert(__METHOD__ == __CLASS__ . '::ass_ert');
	}
}
$mam = new MaMad(); # class names are case insensitive
$mam -> ass_ert();
$mam -> asse_rt();

//assert(__NAMESPACE__ == 'mam_ad');		# TODO: uncomment this line -- note: namespace stmt is not declared