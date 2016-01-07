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
		$count=count($args);
		$index=0;
		$function_variables=[];
		$processed_args=[];
		
		reset($args);
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
					{
						$ref=&$this->variable_reference($argVal);
						$function_variables[$this->name($param)]=$ref;
						$processed_args[]=$ref;
					}
					else //byval
						$processed_args[]=$function_variables[$this->name($param)]=$this->evaluate_expression($argVal);
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
		//process the rest of the arguments passed (i.e variadic arguments)
		for (;$index<$count;++$index)
		{
			if (current($args) instanceof Node) //emulator node
				$processed_args[]=$this->evaluate_expression(current($args)->value);
			else //direct value
				$processed_args[]=&$args[key($args)];
			next($args);
		}
		$this->push();
		$this->variables=$function_variables;
		return $processed_args;
	}
	/**
	 * Runs a procedure (sub).
	 * This is used by all function calling structures, such as run_function, run_method, run_static_method, etc.
	 * This does the prologue and epilogue, sets up arguments and references, and starts execution
	 * @param  Node $function the parsed declaration of function
	 * @param  Node|array $args          args can be either an array of values, or a parsed Node 
	 * @param  array $wrappings 	the parameters to wrap the function call in. An array of key/value pairs that will become current_$key=$value
	 *                           	for the duration of the function call
	 * @param  array $trace_args 	the parameters to be set for the trace of this function call (used in backtrace)
	 * @return mixed return value of function
	 */
	protected function run_function($function,$args,$wrappings=array(),$trace_args=array())
	{
		$processed_args=$this->user_function_prologue($function,$args);
		if ($processed_args===false)
			return null;
		$backups=[];
		foreach ($wrappings as $k=>$v)
		{
			if (!property_exists($this, "current_{$k}"))
				$this->error("Invalid wrapping '{$k}'=>'{$v}'.\n");
			$backups[$k]=$this->{"current_{$k}"};
			$this->{"current_{$k}"}=$v;
		}
		array_push($this->trace, (object)array("args"=>$processed_args, 
			"type"=>"","function"=>$this->current_function,"file"=>$this->current_file,"line"=>$this->current_line));
		foreach ($trace_args as $k=>$v)
			end($this->trace)->$k=$v;
		
		$res=$this->run_code($function->code);
		
		array_pop($this->trace);
		foreach ($backups as $k=>$v)
			$this->{"current_{$k}"}=$v;

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
		$this->verbose("Running {$name}() with ".count($args)." args...".PHP_EOL,2);
		
		//type	string	The current call type. If a method call, "->" is returned. If a static method call, "::" is returned. If a function call, nothing is returned.

		$res=$this->run_function($this->functions[strtolower($name)],$args,
			["file"=>$this->functions[strtolower($name)]->file,"function"=>$name]);

		
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
		$this->stash_ob();
		if ($this->user_function_exists($name)) //user function
			$ret=$this->run_user_function($name,$args); 
		elseif (function_exists($name)) //core function
		{
			$argValues=$this->core_function_prologue($name,$args); #this has to be before the trace line, 
			array_push($this->trace, (object)array("type"=>"","function"=>$name,"file"=>$this->current_file,"line"=>$this->current_line,"args"=>$argValues));
			if (isset($this->mock_functions[strtolower($name)])) //mocked
			{
				$mocked_name=$this->mock_functions[strtolower($name)];
				if (!function_exists($mocked_name))
					$this->error("Mocked function '{$this->mock_functions[$name]}()' not defined to mock '{$name}()'.");
				$this->verbose("Calling mocked function {$mocked_name}() instead of {$name}()...\n",4);
				array_unshift($argValues, $this); //emulator is first argument in mock functions
				$ret=call_user_func_array($mocked_name,$argValues); //core function
			}
			else //original core function
			{
				$this->verbose("Calling core function {$name}()...\n",4);
				#FIXME: not all output in the duration of core function execution is that functions output,
				#		control might come back to emulator and verbose and others used. Do something.
				if (ob_get_level()==0) ob_start();	
				$ret=call_user_func_array($name,$argValues); //core function
				if (ob_get_level()>0) $this->output(ob_get_clean());
			}
			array_pop($this->trace);
		}
		else
			$this->error("Call to undefined function {$name}()",$node);
		$this->restore_ob();
		return $ret;
	}
}
