<?php
$p = 5;
exit(assert($p === 5));	// should print 1
assert($p === 8); // assertion fails if not exited