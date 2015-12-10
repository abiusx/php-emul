<?php
echo $s = shell_exec("ls");
// it seems function call in main.php gets executed before shell exec, so the special code for this never gets used.