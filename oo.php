<?php
require_once __DIR__."/main.php";

class OOEmulator extends Emulator
{
	protected function get_declarations($node)
	{
		if (0)
			;
		else
			parent::get_declarations($node);

	}
	protected function run_statement($node)
	{
		if (0)
			;
		else
			parent::run_statement($node);
	}
	protected function run_code($ast)
	{
		//first pass, get all definitions
		foreach ($ast as $node)
			$this->get_declarations($node);

		//second pass, execute
		foreach ($ast as $node)
		{
			$this->current_node=$node;
			if ($node->getLine()!=$this->current_line)
			{
				$this->current_line=$node->getLine();
				if ($this->verbose) 
					echo "\t\tLine {$this->current_line}",PHP_EOL;
			}
			if ($this->terminated) return null;
			if ($this->return) return $this->return_value;
			$this->run_statement($node);
		}
	}	

}

$x=new OOEmulator;
// $x->start("yapig-0.95b/index.php");
$x->start("sample-stmts.php");
echo "Output of size ".strlen($x->output)." was generated.",PHP_EOL;
var_dump(substr($x->output,-100));