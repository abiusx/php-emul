<?php

echo "This should be seen right now.\n";


ob_start();
echo "5";
$x=ob_get_clean();
echo $x,"=5\n";


ob_start();
echo "3";
ob_start();
echo "2";
ob_start();
echo "1";

$t="";
while (ob_get_level()) $t.=ob_get_clean();
echo $t,"=123\n";


ob_start();
echo "This should be seen.\n";
ob_start();
echo "As well as this.\n";
