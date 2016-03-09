# php-emul
PHP Emul is a PHP Emulator fully written in PHP. It does not require anything beyond the PHP (cli) already available on the system.

PHP Emul completely parses (thanks to PHP Parser), understands and executes all PHP statements and expressions. Only core PHP functions are passed to PHP for execution (e.g strlen). A good number of those are overwritten as well.

80-100 functions are mock (overriden) in the emulator. These are the functions that probe or modify the interpreter (emulator) state, and thus need to reflect emulator internal state instead of the original PHP state. 
These functions are available under mocks/.

a good range of samples used to find bugs and test tricky features of PHP are available under samples/.

Object oriented features are separated from procedural features, by first creating a fully functional procedural Emulator (emulator.php) and 
then extending it under OOEmulator (oo.php). Respective mocks and traits are also separated.

Logically independent features of the emulator are put in traits. The following lists these traits:

* emulator-errors.php handles error reporting, debugging, backtrace and etc.
* emulator-variables.php handles variable management.
* emulator-statements.php handles execution of PHP statements.
* emulator-expressions.php handles evaluation of PHP expressions.
* emulator-functions.php handles function execution and handling.
* oo-methods.php handles method execution and handling.

main.php is the command-line script that can be used to run the emulator. For example, the following line can be used to run a wordpress application:

php main.php -f wordpress/index.php -v 10 --strict && cat output.txt

## PHP Version:

The emulator can emulate PHP 5.4 at the moment. This can be easily changed, by changing the reflected version and adding code for features added
in more recent PHP versions to the emulator.

The emulator can be run under any PHP version >5.4. We suggest running it under PHP 7, which is much faster. For example,
Wordpress homepage runs in 2 seconds under full emulation with PHP 7, but the same code runs for 12 seconds with PHP 5.4 (mostly due to 
memory management and PHP Parser limitations).

Please make the 'cache' folder inside emulator folder writable, so that the emulator can cache parsed files. This will significantly
improve performance (80%+ of execution time is parsing PHP code).

## Limitations:

The following are current limitations:

* Magic methods are not yet implemented. No technical difficulties here, just haven't gotten to it yet.
* Closures are not yet supported by the emulator, although used frequently by the emulator itself.
* Namespaces are not yet supported (the naming scheme needs to be modified for that, which is tightly coupled with the entire code).
* Not all errors caught by PHP are caught by the emulator as well. Basically, the emulator assumes that you are running PHP code that is runnable with
actual PHP (although it checks and enforces 50% of conditions). For example, object property visibility is not yet enforced when accessing.

## Documentation:

The blog post at https://abiusx.com/php-emulator/ covers many aspects of the emulator, and is a great place to start.
