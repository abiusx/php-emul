<?php
echo "\n=====Testing trigger_error functions=====\n";
trigger_error("some user notice!");
echo "This is after notice, and should be seen.\n";
trigger_error("some user error!",E_USER_ERROR);
echo "This is after error, and shouldn't be seen!\n";
