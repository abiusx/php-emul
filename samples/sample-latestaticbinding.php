<?php
class A {
    public static function who() {
        echo __CLASS__;
    }
    public static function test() {
        self::who();
        static::who();
    }
}

class B extends A {
    public static function who() {
        echo __CLASS__;
    }
}

B::test();

echo PHP_EOL,str_repeat("-",40),PHP_EOL;

class Ax {
    public static function foo() {
        static::who();
        self::who();
    }

    public static function who() {
        var_dump(__CLASS__."\n");
    }
}

class Bx extends Ax {
    public static function test() {
    	self::who();
    	static::who();
    	echo PHP_EOL;
        Ax::foo();
        //Late static bindings' resolution will stop at a fully resolved static call with no fallback. 
        //On the other hand, static calls using keywords like parent:: or self:: will forward the calling information.
        parent::foo();
        self::foo();
    }

    public static function who() {
        var_dump( __CLASS__."\n");
    }
}
class Cx extends Bx {
    public static function who() {
        var_dump(__CLASS__."\n");
    }
}

Cx::test();


echo PHP_EOL,str_repeat("-",40),PHP_EOL;

class Aa {
    private function foo() {
        echo "success!\n";
    }
    public function test() {
        $this->foo();
        static::foo();
    }
}

class Ba extends Aa {
   /* foo() will be copied to B, hence its scope will still be A and
    * the call be successful */
}

class Ca extends Aa {
    private function foo() {
    	echo "Cuccess:(\n";
        /* original method is replaced; the scope of the new one is C */
    }
}

$b = new Ba();
$b->test();
$c = new Ca(); //requires method visibility
$c->test();   //fails