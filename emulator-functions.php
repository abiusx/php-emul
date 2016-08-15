<?php
use PhpParser\Node;

trait EmulatorFunctions
{
	/**
 	 * Whether or not an argument is callable, i.e valid syntax and real function name
	 * @param  string  $name 
	 * @return boolean       
	 */
	public function is_callable($name)
	{
		if (is_string($name))
			return $this->function_exists($name);
		else
			return false;
	}
	/**
	 * Whether or not a user function exists
	 * @param  string $f 
	 * @return bool    
	 */
	public function user_function_exists($name)
	{
		$name=strtolower($name);
		$fqname=$this->namespaced_name($name);
		return (array_key_exists($fqname, $this->functions) //in this namespace
		or array_key_exists($name, $this->functions)); //in global namespace
	}
	/**
	 * Whether or not a function exists (user or native)
	 * @param  string $f 
	 * @return bool    
	 */
	public function function_exists($f)
	{
		return function_exists($f) or $this->user_function_exists($f);
	}
	/**
	 * Prepare arguments and symbol table before running a user function
	 * @param  array $function function array from $this->functions
	 * @param  array $args     
	 * @return bool           
	 */
	protected function user_function_prologue($name,$params,$args)
	{
		$count=count($args);
	
		$index=0;
		$function_variables=[];
		$processed_args=[];
		$line=$this->current_line; #TODO: this means of preserving line (also in wrappings), is not very robust
		reset($args);
		foreach ($params as $param)
		{
			if ($index>=$count) //all explicit arguments processed, remainder either defaults or error
			{
				if (isset($param->default))
					$function_variables[$param->name]=$this->evaluate_expression($param->default);
				else
				{
					$this->warning("Missing argument ".($index)." for {$name}()");
					$function_variables[$param->name]=null;
				}

			}
			else //args still available, copy to current symbol table
			{
				if (current($args) instanceof Node)
				{
					$argVal=current($args)->value;
					if ($param->byRef)	// byref handle
					{
						if (!$this->variable_isset($argVal))
							$this->variable_set($argVal);
						$ref=&$this->variable_reference($argVal);
						$function_variables[$this->name($param)]=&$ref;
						$processed_args[]=&$ref;
					}
					else //byval
						$processed_args[]=$function_variables[$this->name($param)]=$this->evaluate_expression($argVal);
				}
				else //direct value, not a Node
				{
					$function_variables[$this->name($param)]=&$args[key($args)]; //byref
					$processed_args[]=&$function_variables[$this->name($param)];
					// $processed_args[]=$function_variables[$this->name($param)]=current($args); //byval, not desired
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
				#TODO: #WEIRD check and make sure this needs to be byref. sometimes byval is the way to go
				$processed_args[]=&$args[key($args)];
			next($args);
		}
		$this->push();
		$this->variables=$function_variables;
		$this->current_line=$line;
		return $processed_args;
	}

	private function context_apply(EmulatorExecutionContext $context)
	{
		$bu_context=new EmulatorExecutionContext;
		foreach ($context as $k=>&$v)
			if (property_exists($context, $k))
			{
				$bu_context->{$k}=$this->{"current_{$k}"};
				$this->{"current_{$k}"}=&$v;
			}
		return $bu_context;
	}
	protected function context_switch(EmulatorExecutionContext $context)
	{
		array_push($this->execution_context_stack, $this->context_apply($context));
	}
	protected function context_restore()
	{
		$this->context_apply(array_pop($this->execution_context_stack)); 
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
	 * @param  array $vars a list of variables to exist in this function scope (e.g. use in closure)
	 * @return mixed return value of function
	 */
	protected function run_function($function,$args,EmulatorExecutionContext $context,$trace_args=array(),$vars=[])
	{
		$name=$trace_args['function'];
		if (isset($trace_args['class']))
			$name=$trace_args['class'].$trace_args['type'].$name;
		$processed_args=$this->user_function_prologue($name,$function->params,$args);
		if ($processed_args===false)
			return null;
		$backups=[];
		//IMPORTANT: these context and backtrace should be set AFTER prologue and BEFORE function execution,
		//because prologue might have expressions that reference the current context.
		array_push($this->trace, (object)array("args"=>$processed_args, 
			"type"=>"","file"=>$this->current_file,"line"=>$this->current_line));
		foreach ($trace_args as $k=>$v)
			end($this->trace)->$k=$v;
		foreach ($vars as $k=>&$v)
			$this->variables[$k]=&$v;
		
		$this->context_switch($context);
		$res=$this->run_code($function->code);
		$this->context_restore();		
		array_pop($this->trace);

		$this->pop(); //pushed in prologue
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
		$function=&$this->functions[strtolower($name)];
		$res=$this->run_function($function,$args,$function->context,["function"=>$name]);//trace
		if ($this->return)
			$this->return=false;	
		return $res;
	}
	/**
	 * Converts array of Node\Arg to array of values
	 * @param  Array of Node $args 
	 * @return array
	 */
	protected function evaluate_args($args)
	{
		$argValues=[];
		foreach ($args as &$arg)
			if (!$arg instanceof Node\Arg)
				$this->error("Argument sent to evaluate_args is not Node\Arg");
			else
				$argValues[]=$this->evaluate_expression($arg->value);
		return $argValues;
	}
	/**
	 * Prologue of a native function
	 * Processes a list of arguments and returns their values
	 * @param  string $name 
	 * @param  array $args 
	 * @param  string $class prologue a class method
	 * @return bool       
	 */
	protected function core_function_prologue($name,$args,$class=null)
	{
		if ($class)
			if (method_exists($class, $name))
				$function_reflection=new ReflectionMethod($class,$name);
			else
				$function_reflection=null; //fallback for __call and others
		else
			if ($name instanceof Closure)
				$function_reflection=null; //nothing for closure
			else
				$function_reflection=new ReflectionFunction($name);

		if ($function_reflection)
			$parameters_reflection=$function_reflection->getParameters();
		else
			$parameters_reflection=[];
		$argValues=[];
		$index=0;
		foreach ($args as &$arg)
		{
			if (isset($parameters_reflection[$index]))
				$parameter_reflection=$parameters_reflection[$index];
			else
				$parameter_reflection=null;
			if ($arg instanceof Node)
			{
				if ($parameter_reflection!==null and $parameter_reflection->isPassedByReference()) //byref 
				{
					if (!$this->variable_isset($arg->value))//should create the variable, like byref return vars
						$this->variable_set($arg->value);
					#has to assign to this, otherwise GC will remove ref before it is used by call_function
					$this->ref=&$this->variable_reference($arg->value); 
					$argValues[]=&$this->ref;
				}
				else
				{
				 	$val=$this->evaluate_expression($arg->value);
					#auto-wrap. note: ReflectionParameter::isCallable always returns false , either in PHP 5.4 or 7.0.2
					#	it probably only works for user-classes
				 	if ( function_exists("callback_requiring_functions") and isset(callback_requiring_functions()[strtolower($name)]) 
				 		and isset(callback_requiring_functions()[strtolower($name)][$index]) ) //its a callback, wrap it!
					{
						$this->verbose("Found a callback in argument {$index} of {$name}(). Wrapping it...\n",5);
						$emul=$this;
						#FIXME: check to see if the callback needs byref/byval args. byref forced now!
						$callback=function(&$arg1=null,&$arg2=null,&$arg3=null,&$arg4=null,&$arg5=null,&$arg6=null,
							&$arg7=null,&$arg8=null,&$arg9=null,&$arg10=null) 
							use ($emul,$val)  
							{
								$argz=debug_backtrace()[0]['args']; //byref hack
								return $emul->call_function($val,$argz);
							};
						$argValues[]=$callback;

					}
					else //byval
						$argValues[]=$val;
				}
			}
			else //direct value
				$argValues[]=&$arg; //byref or byval direct value (not Node)
			$index++;
		}
		return $argValues;
	}
	protected function run_original_core_function($name,$argValues)
	{
		if ($name instanceof Closure)
			$this->verbose("Calling closure...\n",4);
		else
			$this->verbose("Calling core function {$name}()...\n",4);

		#FIXME: not all output in the duration of core function execution is that functions output,
		#		control might come back to emulator and verbose and others used. Do something.
		if (ob_get_level()==0) ob_start();	
		$ret=call_user_func_array($name,$argValues); //core function
		if (ob_get_level()>0) $this->output(ob_get_clean());
		return $ret;
	}
	protected function run_mocked_core_function($name,$argValues)
	{
		$mocked_name=$this->mock_functions[strtolower($name)];
		if (!function_exists($mocked_name))
			$this->error("Mocked function '{$this->mock_functions[$name]}()' not defined to mock '{$name}()'.");
		$this->verbose("Calling mocked function {$mocked_name}() instead of {$name}()...\n",4);
		array_unshift($argValues, $this); //emulator is first argument in mock functions
		$ret=call_user_func_array($mocked_name,$argValues); //core function
		return $ret;
	}
	protected function run_core_function($name,$args)
	{
		$argValues=$this->core_function_prologue($name,$args); #this has to be before the trace line, 
		if ($this->terminated) return null;
		array_push($this->trace, (object)array("type"=>"","function"=>$name,"file"=>$this->current_file,"line"=>$this->current_line,"args"=>$argValues));
		if (isset($this->mock_functions[strtolower($name)])) //mocked
			$ret=$this->run_mocked_core_function($name,$argValues);
		else //original core function
			$ret=$this->run_original_core_function($name,$argValues);
		array_pop($this->trace);
		return $ret;
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
		if ($name instanceof EmulatorClosure)
		{
			$closure=$name;
			$ret=$this->run_function($closure,$args,$closure->context,["function"=>$closure->name],$closure->uses);
			if ($this->return)
				$this->return=false;
			return $ret;
		}
		elseif (function_exists($name)) //global core function
			$ret=$this->run_core_function($name,$args);
		else
		{
			$fqname=$this->namespaced_name($name);
			if ($this->user_function_exists($fqname)) //in this namespace
				$ret=$this->run_user_function($fqname,$args); 
			elseif ($this->user_function_exists($name)) //in global namespace
				$ret=$this->run_user_function($name,$args); 
			// elseif (function_exists($this->current_namespace($name))) //in this namespace core function (shouldn't really happen)
			// 	$ret=$this->run_core_function($this->current_namespace($name),$args);
			else
			{
				$this->error("Call to undefined function {$name}()",$args);
				$ret=null;
			}
		}
		$this->restore_ob();
		return $ret;
	}
}
