<?php
include "jnk/inc1.php";
assert($p === 5);
$p++;

include_once "jnk/inc1.php";
assert($p === 6);

require_once "jnk/inc1.php";
assert($p === 6);


@require "jnk/inc2.php";

assert(6 === 9);	// this should not get exectued

# this file needs more attention