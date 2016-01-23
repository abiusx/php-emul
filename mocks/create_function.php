<?php
function create_function_mock($emul,$args,$code)
{
	static $count=0;
	$name="lambda_".($count++);
	$code="function {$name} ({$args}) {{$code}}";
	$ast=$emul->parser->parse('<?php '.$code);
	$res=$emul->run_code($ast);
	return $name;
}