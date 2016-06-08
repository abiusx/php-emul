<?php
#TODO: PhpParser\Node\Stmt\StaticVar vs PhpParser\Node\Stmt\Static_

use PhpParser\Node;
/**
 * Runs a single statement in the emulator
 */
trait EmulatorStatement 
{
	/**
	 * Used to check if loop condition is still valid
	 * @return boolean
	 */
	private function loop_condition($i=0)
	{
		if ($this->terminated)
			return true;
		if ($this->return)
			return true;
		if ($this->break)
		{
			$this->break--;
			return true;
		}
		if ($this->continue)
		{
			$this->continue--;
			if ($this->continue)
				return true; 
		}
		if ($i>$this->infinite_loop)
		{
			$this->error("Infinite loop");
			return true; 
		}
		return false;
	}
	/**
	 * Runs a single statement
	 * If input is a statement, it will be run. If its an expression, it will be runned like an statement.
	 * @param  Node $node 
	 */
	protected function run_statement($node)
	{
		if ($node instanceof Node\Stmt\Echo_)
			foreach ($node->exprs as $expr)
				$this->output($this->evaluate_expression($expr));
			// $this->output_array($this->evaluate_expression_array($node->exprs));
		elseif ($node instanceof Node\Stmt\Const_)
			return;
		elseif ($node instanceof Node\Stmt\Function_)
			return;
		elseif ($node instanceof Node\Stmt\If_)
		{
			$done=false;
			if ($this->evaluate_expression($node->cond))
			{
				$done=true;
				$this->run_code($node->stmts);
			}
			else
			{
				if (is_array($node->elseifs))
					foreach ($node->elseifs as $elseif)
					{
						if ($this->evaluate_expression($elseif->cond))
						{
							$done=true;
							$this->run_code($elseif->stmts);
							break;
						}
					}
				if (!$done and isset($node->else))
					$this->run_code($node->else->stmts);
			}
		}
		elseif ($node instanceof Node\Stmt\Return_)
		{
			// print_r($node);
			if ($node->expr)
				$this->return_value=$this->evaluate_expression($node->expr);
			else
				$this->return_value=null;
			$this->return=true;
			return $this->return_value;
		}
		elseif ($node instanceof Node\Stmt\For_) //Loop 1
		{
			$i=0;
			$this->loop_depth++;
			for ($this->run_code($node->init);$this->evaluate_expression($node->cond[0]);$this->run_code($node->loop))
			{
				$i++;	
				$this->run_code($node->stmts);
				if ($this->loop_condition($i))
					break;
			}
			$this->loop_depth--;
		}
		elseif ($node instanceof Node\Stmt\While_) //Loop 2
		{
			$i=0;
			$this->loop_depth++;
			while ($this->evaluate_expression($node->cond))
			{
				$i++;
				$this->run_code($node->stmts);
				if ($this->loop_condition($i))
					break;
			}
			$this->loop_depth--;
		}
		elseif ($node instanceof Node\Stmt\Do_) //Loop 3
		{
			$i=0;
			$this->loop_depth++;
			do
			{
				$i++;
				$this->run_code($node->stmts);
				if ($this->loop_condition($i))
					break;
			}
			while ($this->evaluate_expression($node->cond));
			$this->loop_depth--;
		}
		elseif ($node instanceof Node\Stmt\Foreach_) //Loop 4
		{
			$list=$this->evaluate_expression($node->expr);
			$keyed=false;
			if (isset($node->keyVar))
			{
				$keyed=true;	
				if (!$this->variable_isset($node->keyVar))
					$this->variable_set($node->keyVar);
				$keyVar=&$this->variable_reference($node->keyVar);
			}
			if (!$this->variable_isset($node->valueVar))
				$this->variable_set($node->valueVar);
			$valueVar=&$this->variable_reference($node->valueVar);

			$this->loop_depth++;
			if ($this->loop_condition())
				return null; #if already terminated die
			foreach ($list as $k=>$v)
			{
				if ($keyed)
					$keyVar=$k;
				$valueVar=$v;
				$this->run_code($node->stmts);
				
				if ($this->loop_condition())
					break;
			}
			$this->loop_depth--;
		}
		elseif ($node instanceof Node\Stmt\Declare_)
		{
			//TODO: handle tick function here, by wrapping it
			$data=[];
			$code="declare(";
			foreach ($node->declares as $declare)
			{
				$data[$declare->key]=$this->evaluate_expression($declare->value);
				$code.="{$declare->key}='".$this->evaluate_expression($declare->value)."',";
			}
			$code=substr($code,0,-1).");"; 
			eval($code);
		}
		elseif ($node instanceof Node\Stmt\Switch_)
		{
			$arg=$this->evaluate_expression($node->cond);
			$condition=false;
			foreach ($node->cases as $case)
			{
				if ($case->cond===NULL /* default case*/ or $this->evaluate_expression($case->cond)==$arg)
					$condition=true; //run all cases from now forward, until break.
				if ($condition)
					$this->run_code($case->stmts);
				if ($this->loop_condition())
					break;
			}
		} 
		elseif ($node instanceof Node\Stmt\Break_)
		{
			if (isset($node->num))
				$this->break+=$this->evaluate_expression($node->num);
			else
				$this->break++;
		}
		elseif ($node instanceof Node\Stmt\Continue_)
		{
			//basically, continue 3 means break 2 inner loops and continue on the outer loop
			if (isset($node->num))
				$num=$this->evaluate_expression($node->num);
			else
				$num=1;
			$this->continue+=$num;
		}
		elseif ($node instanceof Node\Stmt\Unset_)
		{
			foreach ($node->vars as $var)
				$this->variable_unset($var);
		}
		elseif ($node instanceof Node\Stmt\Throw_)
		{
			if ($this->try>0)
				throw $this->evaluate_expression($node->expr);
			else
				$this->exception_handler($this->evaluate_expression($node->expr));
				// $this->error("Throw that is not catched");

		}
		elseif ($node instanceof Node\Stmt\TryCatch)
		{
			$this->try++;
			try {
				$this->run_code($node->stmts);
			}
			catch (Exception $e)
			{
				$this->try--; //no longer in the try
				foreach ($node->catches as $catch)
				{
					//each has type, the exception type, var, the exception variable, and stmts
					$type=$this->name($catch->type);
					if ($e instanceof $type)
					{
						$this->variable_set($catch->var,$e);
						$this->run_code($catch->stmts);
						break;
					}
				}
				$this->try++; //balance off with the one below
			}
			$this->try--;
		}
		// elseif ($node instanceof Node\Expr\Exit_)
		// {
		// 	$res=$this->evaluate_expression($node->expr);
		// 	if (!is_numeric($res))	
		// 		$this->output($res);
		// 	$this->terminated=true;
		// 	return $res;
		// }
		elseif ($node instanceof Node\Stmt\Static_)
		{
			if (end($this->trace)->type==="" and  isset($this->functions[strtolower(end($this->trace)->function)])) //statc inside a function
			{
				$statics=&$this->functions[strtolower($this->current_function)]->statics;
				foreach ($node->vars as $var)
				{
					$name=$this->name($var->name);
					if (!array_key_exists($name,$statics))
						$statics[$name]=$this->evaluate_expression($var->default);
					$this->variables[$name]=&$statics[$name];
				}
			}
			else
			{
				#TODO:
				$this->todo("Global statics not yet supported");

			}
		}
		elseif ($node instanceof Node\Stmt\InlineHTML)
			$this->output($node->value); 
		elseif ($node instanceof Node\Stmt\Global_)
		{
			foreach ($node->vars as $var)
			{
				$name=$this->name($var->name);
				$globals_=&$this->variable_reference("GLOBALS");

				if (!isset($globals_[$name]))
					$globals_[$name]=null; //create
				$this->variables[$name]=&$globals_[$name];
			}
		}
		elseif ($node instanceof Node\Stmt\Namespace_)
		{
			$backup=$this->current_namespace;
			$this->current_namespace=$this->name($node);
			$this->verbose("Changing namespace to '{$this->current_namespace}'...\n",2);
			$res=$this->run_code($node->stmts);
			$namespace_name="'$backup'";
			if ($namespace_name=="''")
				$namespace_name="default";
			$this->verbose("Restoring namespace to {$namespace_name}...\n",2);
			$this->current_namespace=$backup;
			return $res;

		}
		elseif ($node instanceof Node\Expr)
			$this->evaluate_expression($node);
		else
		{
			$this->error("Unknown node type: ",$node);	
		}
	}
}