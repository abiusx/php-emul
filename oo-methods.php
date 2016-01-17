<?php
use PhpParser\Node;

trait OOEmulatorMethodExistence {
	/**
	 * Whether or not a user defined class exists
	 * @param  string $classname 
	 * @return bool
	 */
	public function user_class_exists($classname)
	{
		return isset($this->classes[strtolower($classname)]);
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
	
	/**
	 * Checks whether a user class has a direct property or not
	 * @param  [type] $class    [description]
	 * @param  [type] $property [description]
	 * @return [type]           [description]
	 */
	public function user_property_exists($class,$property)
	{
		if (!isset($this->classes[strtolower($class)])) return false;
		var_dump($this->classes[strtolower($class)]->properties);
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
				return isset($class_or_obj->properties[$property]);
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
	 * Whether or not a method exists in a class
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
		if (array_key_exists(strtolower($class_name), $this->classes))
			return $this->run_user_static_method($original_class_name,$method_name,$args);
		elseif (class_exists($class_name))
			return call_user_func_array($class_name."::".$method_name, $args);
		else
			$this->error("Can not call static method '{$class_name}::{$method_name}', class '{$original_class_name}' does not exist.\n");

	}
	/**
	 * Runs a static method of a user defined class
	 * @param  string $original_class_name 
	 * @param  string $method_name         
	 * @param  array $args    
	 * @param  object &$object optional whether or not an object ($this) should be set.
	 * @return mixed                      
	 */
	protected function run_user_static_method($original_class_name,$method_name,$args,&$object=null)
	{
		$class_name=$this->real_class($original_class_name);
		if ($this->verbose)
			$this->verbose("Running {$class_name}::{$method_name}()...".PHP_EOL,2);
		$flag=false;
		foreach ($this->ancestry($class_name) as $class)
		{
			if ($this->user_method_exists($class,$method_name))
			{
				if ($class==$class_name)
					$word="direct";
				else
					$word="ancestor";
				$this->verbose("Found {$word} method {$class}::{$method_name}()...".PHP_EOL,3);
				$trace_args=array("type"=>"::","function"=>$method_name,"class"=>$class);
				$wrappings=["method"=>$method_name,"class"=>$class_name,"self"=>$class,"file"=>$this->classes[strtolower($class)]->file,"line"=>$this->current_line];
				if ($object!==null)
				{
					$trace_args['object']=&$object;
					$trace_args['type']="->";
					$wrappings['this']=&$object;
				}
				$res=$this->run_function($this->classes[strtolower($class)]->methods[strtolower($method_name)],$args, $wrappings, $trace_args);
				$flag=true;
				break;	
			}

		}
		if (!$flag)
		{
			$this->error("Call to undefined method {$class_name}::{$method_name}()");
			$res=null;
		}
		if ($this->return)
			$this->return=false;	
		return $res;
	}
	/**
	 * Runs a method on an object (whether user defined or core)
	 * @param  object &$object     
	 * @param  string $method_name 
	 * @param  array $args        
	 * @return mixed              
	 */
	protected function run_method(&$object,$method_name,$args)
	{
		if ($object instanceof EmulatorObject)
			return $this->run_user_method($object,$method_name,$args);
		elseif (is_object($object))
			#TODO: add mocked class/methods, and also add trace here.
			return call_user_func_array(array($object,$method_name), $args);
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
	protected function run_user_method(&$object,$method_name,$args)
	{
		if (!($object instanceof EmulatorObject))
		{
			$this->error("Inconsistency in object oriented emulation. A malformed object detected.",$object);
			return null;
		}
		$class_name=$object->classname;
		
		$res=$this->run_user_static_method($class_name,$method_name,$args,$object);
		return $res;
	}


}