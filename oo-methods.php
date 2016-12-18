<?php
use PhpParser\Node;
trait OOEmulatorMethodExistence {
	/**
	 * Whether or not a user defined classlike (interface, trait, class, etc.) exists
	 * @param  string $classname 
	 * @return bool
	 */
	public function user_classlike_exists($classname,$type=null)
	{
		return isset($this->classes[strtolower($classname)]) and ($type===null or $this->classes[strtolower($classname)]->type==$type);
	}
	public function user_class_exists($classname)
	{
		return $this->user_classlike_exists($classname,"class");
	}
	public function user_interface_exists($classname)
	{
		return $this->user_classlike_exists($classname,"interface");
	}
	public function user_trait_exists($classname)
	{
		return $this->user_classlike_exists($classname,"trait");
	}
	/**
	 * Whether or not a class exists, either core class or user class
	 * @param  string $classname 
	 * @return bool            
	 */
	public function class_exists($classname)
	{
		return class_exists($classname) or $this->user_class_exists($classname);
	}
	public function interface_exists($interface)
	{
		return interface_exists($interface) or $this->user_interface_exists($interface);
	}
	
	/**
	 * Checks whether a user class has a direct property or not
	 * @param  [type] $class    [description]
	 * @param  [type] $property [description]
	 * @return [type]           [description]
	 */
	public function user_property_exists($class,$property)
	{
		if (!isset($this->classes[strtolower($class)])) return false;
		$this->stash_ob();
		return isset($this->classes[strtolower($class)]->properties[$property]); //this is case sensitive
	}

	/**
	 * Checks whether a class (including its ancestors) or an object has a property or not, 
	 * @param  [type] $class_or_obj [description]
	 * @param  [type] $property     [description]
	 * @return [type]               [description]
	 */
	public function property_exists($class_or_obj,$property)
	{
		if (!is_string($class_or_obj))
		{
			if ($class_or_obj instanceof EmulatorObject)
				return array_key_exists($property, $class_or_obj->properties);
				// return isset($class_or_obj->properties[$property]);
			else
				return property_exists($class_or_obj, $property);
		}
		$class=$class_or_obj;	
		if (!$this->user_class_exists($class)) return property_exists($class,$methodname); //internal php class
		foreach ($this->ancestry($class) as $ancestor)
			if ($this->user_property_exists($ancestor,$property)) return true;
		return false;
	}


	/**
	 * Equivalent to PHP's method_exists
	 * Does check ancestry
	 * @param  mixed $class_or_obj [description]
	 * @param  string $methodname          [description]
	 * @return bool                      [description]
	 */
	public function method_exists($class_or_obj,$methodname)
	{
		if (!is_string($class_or_obj))
			$class=$this->get_class($class_or_obj);
		else
			$class=$class_or_obj;
		if (!$this->user_class_exists($class)) return method_exists($class,$methodname); //internal php class
		foreach ($this->ancestry($class) as $ancestor)
			if ($this->user_method_exists($ancestor,$methodname)) return true;
		return false;
	}
	/**
	 * Whether or not a method exists DIRECTLY in a class
	 * Does not check ancestry.
	 * WARNING: does not check whether the method is really static or not
	 * @param  string $classname  
	 * @param  string $methodname 
	 * @return bool             
	 */
	public function static_method_exists($classname,$methodname)
	{
		return method_exists($classname, $methodname) or $this->user_method_exists($classname,$methodname);
	}	
	/**
	 * Whether or not a user defined method (of a specific class) exists
	 * Does not check ancestry.
	 * WARNING: does not check whether the method is static or not
	 * @param  string $classname  
	 * @param  string $methodname 
	 * @return bool             
	 */
	public function user_method_exists($classname,$methodname)
	{
		if (!$this->user_class_exists($classname)) return false;
		#TODO: separate static/instance methods?
		return isset($this->classes[strtolower($classname)]->methods[strtolower($methodname)]);
	}



	public function get_parent_class($obj)
	{
		if (!is_object($obj)) return null;
		if ($obj instanceof EmulatorObject)
		{
			$class=$obj->classname;
			if (!isset($this->classes[strtolower($class)]))
				return null;
			return $this->classes[strtolower($class)]->parent;
		}
		else
			return get_parent_class($obj);
	}
	/**
	 * Get the classname of an object (either user defined or core)
	 * @param  object $obj 
	 * @return string      
	 */
	public function get_class($obj)
	{
		if (!is_object($obj)) return null;
		if ($obj instanceof EmulatorObject)
			$class=$obj->classname;
		else
			$class=get_class($obj);
		return $class;
	}
	/**
	 * Whether or not an object is an instance of a user defined class
	 * @param  mixed  $obj 
	 * @return boolean      
	 */
	public function is_user_object($obj)
	{
		return $obj instanceof EmulatorObject;
	}
	/**
	 * Whether or not an input is an object (user defined or core)
	 * @param  mixed  $obj 
	 * @return boolean      
	 */
	public function is_object($obj)
	{
		return is_object($obj) or $this->is_user_object($obj);
	}

	/**
	 * Whether or not an argument is callable, i.e valid syntax and valid function/method/class names
	 * @param  string  $x 
	 * @return boolean    
	 */
	public function is_callable($x)
	{
		if (is_string($x))
		{
			if (strpos($x,"::")!==false)
			{
				list($classname,$methodname)=explode("::",$x);
				return ($this->class_exists($classname) and $this->method_exists($classname, $methodname));
			}
			else
				return parent::is_callable($x);
		}
		elseif (is_array($x) and count($x)==2 and isset($x[0]) and isset($x[1]))
		{
			if (is_string($x[0]))
				return $this->class_exists($x[0]) and $this->method_exists($x[0], $x[1]);
			else
				return $this->is_object($x[0]) and $this->method_exists($x[0],$x[1]);
		}
		else 
			return parent::is_callable($x);
	}
}
trait OOEmulatorMethods {
	use OOEmulatorMethodExistence;

	/**
	 * Runs a static method of a class
	 * @param  $original_class_name original because it can be self,parent, etc.
	 * @param  string $method_name         
	 * @param  array $args                
	 * @return mixed return result of the function                      
	 */
	protected function run_static_method($original_class_name,$method_name,$args)
	{
		$class_name=$this->real_class($original_class_name);
		if (!$this->class_exists($class_name))	
			$this->spl_autoload_call($class_name);
		if (array_key_exists(strtolower($class_name), $this->classes))
			return $this->run_user_static_method($original_class_name,$method_name,$args);
		elseif (class_exists($class_name))
			return $this->run_core_static_method($original_class_name,$method_name,$args);
			// return call_user_func_array($class_name."::".$method_name, $args);
		else
		{
			$this->error("Can not call static method '{$class_name}::{$method_name}', class '{$class_name}' does not exist.\n");
		}

	}
	/**
	 * Runs a static method of a user defined class
	 * @param  string $original_class_name 
	 * @param  string $method_name         
	 * @param  array $args    
	 * @param  object &$object optional whether or not an object ($this) should be set.
	 * @return mixed                      
	 */
	protected function run_user_static_method($original_class_name,$method_name,$args=[],&$object=null)
	{
		$class_name=$this->real_class($original_class_name);
		$indirect=($class_name!==$original_class_name); //whether its class::x() vs self::x() or the like
		$this->verbose("Running {$class_name}::{$method_name}()...".PHP_EOL,2);
		$flag=false;
		foreach ([$method_name,'__call',"__callStatic"] as $method) //magic methods
		{
			if ($flag) break;
			foreach ($this->ancestry($class_name) as $class)
			{
				if ($method=="__call")
				{
					if ($object===null)	continue;
					$this->verbose("Method not found, trying '__call' magic method...\n",3);
				}
				elseif ($method=="__callStatic")
				{
					if ($object!==null)	continue;
					$this->verbose("Method not found, trying '__callStatic' magic method...\n",3);
				}
				if ($this->user_method_exists($class,$method))
				{
					if ($class==$class_name)
						$word="direct";
					else
						$word="ancestor";
					$this->verbose("Found {$word} method {$class}::{$method}()...".PHP_EOL,3);
					$trace_args=array("type"=>"::","function"=>$method,"class"=>$class);
					$class_index=&$this->classes[strtolower($class)];
					$context=clone $class_index->context;
					$context->method=$method;
					$context->line=$this->current_line;
					if (!$indirect) //do not update on indirects http://php.net/manual/en/language.oop5.late-static-bindings.php
					{
						$context->class=$class_name; //late static bind
						$this->verbose("Setting class to '{$class_name}' and self to '{$class}'...\n",5);
					}
					else
						$this->verbose("Setting 'self' to '{$class}' and not changing 'class' ('{$this->current_class}')...\n",5);
					$context->self=$class; //self

					if ($object!==null)
					{
						$trace_args['object']=$object; //do we need ref here? I don't think so
						$trace_args['type']="->";
						$context->this=$object; //do we need ref here? I don't think so
					}
					if ($method=="__call" or $method=="__callStatic") 
					{
						// $argz=$this->core_function_prologue($method,$args,$class);
						$argz=$this->evaluate_args($args);
						$argz=[$method_name,$argz];
					}
					else
						$argz=$args;
					$res=$this->run_function($class_index->methods[strtolower($method)],$argz, $context, $trace_args);
					$flag=true;
					break;	
				}
				elseif (class_exists($class)) //core class
				{
					if ($object and $object->parent and method_exists($object->parent, $method))
					{
						$res=$this->run_core_static_method($class,$method,$args,$object->parent);
						$flag=true;
						break;
					}
				}

			}
		}
		if (!$flag) //method not found
		{
			$this->error("Call to undefined method {$class_name}::{$method_name}()");
			$res=null;
		}
		if ($this->return)
			$this->return=false;	
		return $res;
	}
	/**
	 * Runs methods on core classes on objects
	 * @param  string $class_name  can be parent, self, etc.
	 * @param  [type] $method_name [description]
	 * @param  [type] $args        [description]
	 * @param  [type] &$object     optional, for non-static method call
	 * @return [type]              [description]
	 */
	function run_core_static_method($class_name,$method_name,$args,&$object=null)
	{
		$class=$this->real_class($class_name);
		$argValues=$this->core_function_prologue($method_name,$args,$class); #this has to be before the trace line, 
		if ($this->terminated) return null;
		#TODO: add mocked class/methods
		
		if ($object!==null) //method
		{
			$this->verbose("Core method has a bound object, calling...\n",4);
			array_push($this->trace, (object)array("type"=>"->","function"=>$method_name,"file"=>$this->current_file,"line"=>$this->current_line,"args"=>$argValues));
			$ret=call_user_func_array(array($object,$method_name), $argValues);
			array_pop($this->trace);

		}
		else 		//static
		{
			$this->verbose("Core method is static-like, calling...\n",4);
			if (method_exists($class, $method_name))
			{
				$r=new ReflectionMethod($class,$method_name);
				if ($r->isStatic())
				{
					array_push($this->trace, (object)array("type"=>"::","function"=>$method_name,"file"=>$this->current_file,"line"=>$this->current_line,"args"=>$argValues));
					$ret=call_user_func_array($class."::".$method_name, $argValues);
					array_pop($this->trace);
				}
				elseif ($this->current_this and $this->current_this->parent) //call with this
				{
					array_push($this->trace, (object)array("type"=>"->","function"=>$method_name,"file"=>$this->current_file,"line"=>$this->current_line,"args"=>$argValues));
					$ret=call_user_func_array(array($this->current_this->parent,$method_name), $argValues);
					array_pop($this->trace);
				}
				else
					$this->error("Non-static method {$class}::{$method_name}() cannot be called statically");

			}
		}

		return $ret;

	}
	/**
	 * Runs a method on an object (whether user defined or core)
	 * @param  object &$object     
	 * @param  string $method_name 
	 * @param  array $args        
	 * @return mixed              
	 */
	public function run_method(&$object,$method_name,$args=[],$class_name=null)
	{
		if ($object instanceof EmulatorObject)
			return $this->run_user_method($object,$method_name,$args,$class_name);
		elseif (is_object($object))
			return $this->run_core_static_method(get_class($object),$method_name,$args,$object);
			// return call_user_func_array(array($object,$method_name), $args);
		else
			$this->error("Can not call method '{$method_name}' on a non-object.\n",$object);
	}
	/**
	 * Runs a user defined class' object's method
	 * @param  object &$object     
	 * @param  string $method_name 
	 * @param  array $args        
	 * @return mixed              
	 */
	protected function run_user_method(&$object,$method_name,$args=[],$class_name=null)
	{
		if (!($object instanceof EmulatorObject))
		{
			$this->error("Inconsistency in object oriented emulation. A malformed object detected.",$object);
			return null;
		}
		if ($class_name===null)
			$class_name=$object->classname;
		
		$res=$this->run_user_static_method($class_name,$method_name,$args,$object);
		return $res;
	}


}