<?php
eval('@$p = jafar;');
assert($p === 'jafar');

eval('echo "hi";'); // outputs 'hi'
eval('?>echo "hi";'); // outputs 'echo "hi";'