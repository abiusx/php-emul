<?php
class Foo {
  private static $used=0;
  public $id;
  public function __construct() {
    $this->id = self::$used++;
    echo "Construct {$this->id}",PHP_EOL;
  }
  public function __clone() {
    $this->id = self::$used++;
    echo "Clone {$this->id}",PHP_EOL;
  }
  public function __destruct() {
    echo "Destruct {$this->id}",PHP_EOL;
  }
}
// for ($i=0;$i<10;++$i)
  // $x=new Foo();
function spl($obj)
{
  return "*".strtoupper(substr(md5(spl_object_hash($obj)),-4));
}

$a = new Foo; // $a is a pointer pointing to Foo object 0
echo $a->id,PHP_EOL;
echo spl($a),PHP_EOL,PHP_EOL;
$b = $a; // $b is a pointer pointing to Foo object 0, however, $b is a copy of $a
echo $b->id,PHP_EOL;
echo spl($b),PHP_EOL,PHP_EOL;
$c = &$a; // $c and $a are now references of a pointer pointing to Foo object 0
echo $c->id,PHP_EOL;
echo spl($c),PHP_EOL,PHP_EOL;
$a = new Foo; // $a and $c are now references of a pointer pointing to Foo object 1, $b is still a pointer pointing to Foo object 0
echo $a->id,PHP_EOL;
echo spl($a),PHP_EOL,PHP_EOL;
echo $b->id,PHP_EOL;
echo spl($b),PHP_EOL,PHP_EOL;
echo $c->id,PHP_EOL;
echo spl($c),PHP_EOL,PHP_EOL;

unset($a); // A reference with reference count 1 is automatically converted back to a value. Now $c is a pointer to Foo object 1
echo "---\n";
echo $b->id,PHP_EOL;
echo spl($b),PHP_EOL,PHP_EOL;
echo $c->id,PHP_EOL;
echo spl($c),PHP_EOL,PHP_EOL;
echo "---\n";
$a = &$b; // $a and $b are now references of a pointer pointing to Foo object 0
echo $a->id,PHP_EOL;
echo $b->id,PHP_EOL;
echo $c->id,PHP_EOL;
echo "---\n";
$a = NULL; // $a and $b now become a reference to NULL. Foo object 0 can be garbage collected now
unset($b); // $b no longer exists and $a is now NULL
$a = clone $c; // $a is now a pointer to Foo object 2, $c remains a pointer to Foo object 1
echo $a->id,PHP_EOL;
echo $c->id,PHP_EOL;
unset($c); // Foo object 1 can be garbage collected now.
$c = $a; // $c and $a are pointers pointing to Foo object 2
echo $a->id,PHP_EOL;
echo $c->id,PHP_EOL;
echo "---\n";
unset($a); // Foo object 2 is still pointed by $c
$a = &$c; // Foo object 2 has 1 pointers pointing to it only, that pointer has 2 references: $a and $c;
echo $a->id,PHP_EOL;
echo $c->id,PHP_EOL;
const ABC = TRUE;
if(ABC) {
  $a = NULL; // Foo object 2 can be garbage collected now because $a and $c are now a reference to the same NULL value
} else {
  unset($a); // Foo object 2 is still pointed to $c
}