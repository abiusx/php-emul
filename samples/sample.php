<?php
session_start();
die();
echo "Hi";
$x=5+2.0;
$x=(int)($x/3);
$y="abc{$x}efg";
if ($x!=5)
	$x=123;
elseif ($x>5)
	$x=$x+1;
// else
	// $x=$x-1;
// echo $y.$x;
echo $x,PHP_EOL;
function f($x)
{
	return $x;
}
echo f("zart");
function stringtoURL($string,$set=TRUE){ 
    $strPos = strpos($string,'?'); 
    $str = substr($string,$strPos+1); 
    $groups = explode('&',$str); 
    $nSet = array(); 
    foreach($groups as $st){ 
        list($name,$var) = explode('=',$st); 
        if($set){ 
            $_GET[$name] = $var; 
        }else{ 
            $nSet[$name] = $var; 
        } 
    } 
    if(!$set){ 
        return $nSet; 
    } 
} 
// Version 1 
// Convert string to $_GET variables 
$s = 'http://tzfiles.com/?name=bob&str=hello&q=awesome'; 
stringtoURL($s); 
exit(0);
echo $_GET['name'].'<br />'; 
echo $_GET['str'].'<br />'; 
echo $_GET['q'].'<br />'; 

// Version 2 
// Convert string to an array 
$s = 'http://tzfiles.com/?name=bob&str=hello&q=awesome'; 
print_r($s,false); 
print_r(stringtoURL($s,false),1); 