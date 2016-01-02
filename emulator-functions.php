<?php
#TODO: use ReflectionParameter::isCallable to auto-wrap callbacks for core functions

use PhpParser\Node;

trait EmulatorFunctions
{
	/**
	 * Whether or not a user function exists
	 * @param  string $f 
	 * @return bool    
	 */
	public function user_function_exists($f)
	{
		return isset($this->functions[strtolower($f)]);	
	}
	/**
	 * Whether or not a function exists (user or native)
	 * @param  string $f 
	 * @return bool    
	 */
	public function function_exists($f)
	{
		return function_exists($f) or isset($this->functions[strtolower($f)]);
	}
	/**
	 * Prepare arguments and symbol table before running a user function
	 * @param  array $function function array from $this->functions
	 * @param  array $args     
	 * @return bool           
	 */
	protected function user_function_prologue($function,$args)
	{
		reset($args);
		$count=count($args);
		$index=0;
		$function_variables=[];
		foreach ($function->params as $param)
		{
			if ($index>=$count) //all explicit arguments processed, remainder either defaults or error
			{
				if (isset($param->default))
					$function_variables[$param->name]=$this->evaluate_expression($param->default);
				else
				{
					$this->warning("Missing argument ".($index)." for {$name}()");
					return false;
				}

			}
			else //args still available, copy to current symbol table
			{
				if (current($args) instanceof Node)
				{
					$argVal=current($args)->value;
					if ($param->byRef)	// byref handle
						$function_variables[$this->name($param)]=&$this->variable_reference($argVal);
					else //byval
						$function_variables[$this->name($param)]=$this->evaluate_expression($argVal);
				}
				else //direct value, not a Node
				{
					$function_variables[$this->name($param)]=&$args[key($args)]; //byref
					// $t=current($args); //byval, not desired
				}
				next($args);
			}
			$index++;
		}
		$this->push();
		$this->variables=$function_variables;
		end($this->trace)->args=$function_variables;
		return true;
	}
	/**
	 * Runs a procedure (sub).
	 * This is used by all function calling structures, such as run_function, run_method, run_static_method, etc.
	 * This does the prologue and epilogue, sets up arguments and references, and starts execution
	 * @param  Node $function the parsed declaration of function
	 * @param  Node|array $args          args can be either an array of values, or a parsed Node 
	 * @return mixed return value of function
	 */
	protected function run_function($function,$args)
	{
		if (!$this->user_function_prologue($function,$args))
			return null;
		$res=$this->run_code($function->code);
		$this->pop();
		return $res;
	}
	/**
	 * Runs a user-defined (emulated) function
	 * @param  string $name [description]
	 * @param  Node $args should be a parsed node
	 * @return mixed
	 */
	protected function run_user_function($name,$args)
	{
		$this->verbose("Running {$name}()...".PHP_EOL,2);
		
		$last_function=$this->current_function;
		$this->current_function=$name;
		//type	string	The current call type. If a method call, "->" is returned. If a static method call, "::" is returned. If a function call, nothing is returned.
		array_push($this->trace, (object)array("type"=>"function","name"=>$this->current_function,"file"=>$this->current_file,"line"=>$this->current_line));
		$last_file=$this->current_file;
		$this->current_file=$this->functions[strtolower($name)]->file;

		$res=$this->run_function($this->functions[strtolower($name)],$args);

		array_pop($this->trace);
		$this->current_function=$last_function;
		$this->current_file=$last_file;
		
		if ($this->return)
			$this->return=false;	
		return $res;
	}
	/**
	 * Prologue of a native function
	 * @param  string $name 
	 * @param  array $args 
	 * @return bool       
	 */
	protected function core_function_prologue($name,$args)
	{
		#TODO: auto-wrap callables, they are used all over the place
		$function_reflection=new ReflectionFunction($name);
		$parameters_reflection=$function_reflection->getParameters();
		$argValues=[];
		foreach ($args as &$arg)
		{
			$parameter_reflection=current($parameters_reflection);
			if ($arg instanceof Node)
			{
				if ($parameter_reflection->isPassedByReference()) //byref 
				{
					if (!$this->variable_isset($arg->value))//should create the variable, like byref return vars
						$this->variable_set($arg->value);
					$argValues[]=&$this->variable_reference($arg->value); 
				}
				else //byval
					$argValues[]=$this->evaluate_expression($arg->value);
			}
			else //direct value
				$argValues[]=&$arg; //byref or byval direct value (not Node)
			next($parameters_reflection);
		}
		return $argValues;
	}
	/**
	 * Runs a function, whether its internal or emulated.
	 * @param  string $name [description]
	 * @param  array $args parsed node or array of values
	 * @return mixed
	 */
	public function call_function($name,$args)
	{
		if ($this->user_function_exists($name)) //user function
			return $this->run_user_function($name,$args); 
		elseif (function_exists($name)) //core function
		{
			$argValues=$this->core_function_prologue($name,$args);
			if (isset($this->mock_functions[strtolower($name)])) //mocked
			{
				$mocked_name=$this->mock_functions[strtolower($name)];
				if (!function_exists($mocked_name))
					$this->error("Mocked function '{$this->mock_functions[$name]}()' not defined to mock '{$name}()'.");
				$this->verbose("Calling core function {$name}() mocked as {$mocked_name}()...\n",4);
				array_unshift($argValues, $this); //emulator is first argument in mock functions
				$ret=call_user_func_array($mocked_name,$argValues); //core function
			}
			else //original core function
			{
				$this->verbose("Calling core function {$name}()...\n",4);
				ob_start();	
				$ret=call_user_func_array($name,$argValues); //core function
				$output=ob_get_clean();
				$this->output($output);
			}
			return $ret;
		}
		else
			$this->error("Call to undefined function {$name}()",$node);
	}
}
