<?php
$x=0;
for ($i=0;$i<100;++$i)
{
	$x++;
	continue;
	// break;
	$x++;
}
var_dump($x);