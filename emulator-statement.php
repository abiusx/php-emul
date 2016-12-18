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
	protected function loop_condition($i=0)
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
			//php 5.5+ support byref even if the expression is not byref itself (via a temporary replacement)
			//TODO: test all foreach compilations with list
			$byref=$node->byRef;
			if ($node->expr instanceof Node\Expr\Variable and $byref)
				$list=&$this->variable_reference($node->expr);
			else
				$list=$this->evaluate_expression($node->expr);

			$keyed=false;
			//OO code here, to prevent double evaluation of list
			if ($list instanceof EmulatorObject)
				$list=$list->properties;
			if (isset($node->keyVar))
			{
				$keyed=true;	
				if (!$this->variable_isset($node->keyVar))
					$this->variable_set($node->keyVar);
				$keyVar=&$this->variable_reference($node->keyVar);
			}
			// if (!$this->variable_isset($node->valueVar))
			// 	$this->variable_set($node->valueVar);
			// $valueVar=&$this->variable_reference($node->valueVar);
			$this->loop_depth++;
			if ($this->loop_condition())
				return null; #if already terminated die
			if ($byref)
			foreach ($list as $k=>&$v)
			{
				if ($keyed)
					$keyVar=$k;
				$this->variable_set_byref($node->valueVar,$v);	
				$this->run_code($node->stmts);
				
				if ($this->loop_condition())
					break;
			}
			else
			foreach ($list as $k=>$v)
			{
				if ($keyed)
					$keyVar=$k;
				$this->variable_set($node->valueVar,$v);
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
			$this->throw_exception($this->evaluate_expression($node->expr));
		}
		elseif ($node instanceof Node\Stmt\TryCatch)
		{
			$framecount=count($this->trace);
			$this->try++;
			try {
				$this->verbose("Starting a Try block (depth:{$this->try})...\n",2);
				$this->run_code($node->stmts);
				$this->verbose("Ending a Try block without error (depth:{$this->try})...\n",3);
			}
			catch (Exception $e)
			{
				$diff=count($this->trace)-$framecount;
				if ($diff>0)
				{
					$this->verbose("Exception of type '".get_class($e)."' cautght, restoring context...\n",2);

					for ($i=0;$i<$diff;++$i)
					{
						$this->context_restore();
						array_pop($this->trace);
						array_pop($this->variable_stack);
					}
					$this->reference_variables_to_stack();
					$this->verbose("Context restored prior to running catch block ({$diff} stack frames).\n",3);
				}
				else
					$this->verbose("Exception of type '".get_class($e)."' caught, and no context restoration needed.\n",2);
				$catch_found=false;
				$this->try--; //no longer in the try
				$this->verbose("Attempting to find matchin Catch block...\n",3);
				foreach ($node->catches as $catch)
				{
					//each has type, the exception type, var, the exception variable, and stmts
					$type=$this->name($catch->type);
					if ($e instanceof EmulatedException and $this->is_a($e->object,$type)) //user-defined exception
					{
						$this->verbose("Catch block (user-defined exception type) found, executing...\n",4);
						$this->variable_set($catch->var,$e->object);
						$this->run_code($catch->stmts);
						$catch_found=true;
						break;
					}
					elseif ($e instanceof $type)
					{
						$this->verbose("Catch block found, executing...\n",4);
						$this->variable_set($catch->var,$e);
						$this->run_code($catch->stmts);
						$catch_found=true;
						break;
					}
				}
				if ($catch_found)
					$this->verbose("Catch block complete.\n",3);
				else
				{
					$this->verbose("Could not find any matching catch block, throwing error for further catching...\n",3);
					$this->throw_exception($e);
				}
				$this->try++; //balance off with the one below
			}
			#TODO: handle finally
			$this->try--;
		}
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
				// $this->verbose("Aliasing global variable '\${$name}'...\n",4);
				$this->variables[$name]=&$globals_[$name];
			}
		}
		elseif ($node instanceof Node\Stmt\Namespace_)
		{
			//namespaces are not nested, and if used, there is no code outside namespace
			//so all of this files is namespace statement(s)

			$this->current_namespace=$this->name($node);
			$this->current_active_namespaces=[];

			$this->verbose("Changing namespace to '{$this->current_namespace}'...\n",2);
			$this->run_code($node->stmts);

		}
		elseif ($node instanceof Node\Stmt\Const_) //constants are not declared ahead of time, they are inline
		{
			#constant definition:
			foreach ($node->consts as $const)
				$this->constant_set($const->name,$this->evaluate_expression($const->value));
		}

		elseif ($node instanceof Node\Stmt\Use_)
		;
		elseif ($node instanceof Node\Expr)
			$this->evaluate_expression($node);
		else
		{
			$this->error("Unknown node type: ",$node);	
		}
	}
	function constant_exists($name)
	{
		if (defined($name)) return true;
		$fqname=$this->namespaced_name($name);
		return (array_key_exists($fqname, $this->constants))
			or	(array_key_exists($name, $this->constants));
	}
	function constant_get($name)
	{
		if (defined($name))
			return constant($name);
		$fqname=$this->namespaced_name($name);
		if (array_key_exists($fqname, $this->constants))
			return $this->constants[$fqname];
		elseif (array_key_exists($name, $this->constants))
			return $this->constants[$name];
		else
		{
			if (is_string($fqname))
			{
				$this->notice("Use of undefined constant {$fqname} - assumed '{$fqname}'");
				return $fqname;
			}	
			else
				$this->error("Undefined constant {$fqname}");
		}
	}
	function constant_set($name,$value)
	{
		$index=$this->current_namespace($name);
		if (array_key_exists($index,$this->constants))
			$this->notice("Constant {$index} already defined");
		else
			$this->constants[$index]=$value;
	}
}