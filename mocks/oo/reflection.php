<?php
abstract class BaseReflection_mock 
{
	public $reflection=null;
	static public $emul;
	function emul()
	{
		return self::$emul;
	}
	function __call($name,$args)
	{
		if ($this->reflection)
			return call_user_func_array(array($this->reflection,$name), $args);
		elseif (method_exists($this, "_".$name))
			return call_user_func_array(array($this,"_".$name), $args);
		$this->emul()->error(get_class($this)."::{$name}() is not yet implemented ");
	}
}
class ReflectionMethod_mock extends BaseReflection_mock
{
	protected $class,$method;
	function &myclass()
	{
		return $this->emul()->classes[strtolower($this->class)];
	}
	function &method()
	{
		return $this->emul()->classes[strtolower($this->class)]->methods[strtolower($this->method)];
	}
	function __construct($class,$method=null)
	{
		if ($method===null)
			list($class,$method)=explode("::",$class);
		elseif (is_object($class))
			if ($class instanceof EmulatorObject)
				$class=$class->classname;
			else
				return $this->reflection=new ReflectionMethod($class,$method);
		if ($this->emul()->user_class_exists($class))
			$this->class=$class;
		else
			$this->reflection=new ReflectionMethod($class,$method);
		$this->method=$method;
	}

	function _getName()
	{
		return $this->method()->name;
	}
}
class ReflectionProperty_mock extends BaseReflection_mock
{
	protected $prop,$class;
	function &myclass()
	{
		return $this->emul()->classes[strtolower($this->class)];

	}
	function _isPrivate()
	{
		// var_dump($this->myclass()->property_visibilities[$this->prop]);
		return $this->myclass()->property_visibilities[$this->prop]==EmulatorObject::Visibility_Private;
	}
	function __construct($class,$name)
	{
		$this->class=$class;
		$this->prop=$name;
	}
}
class ReflectionClass_mock extends BaseReflection_mock
{
	protected $class="";
	function &myclass()
	{
		return $this->emul()->classes[strtolower($this->class)];

	}
	function __construct($arg)
	{
		if (is_object($arg))
			if ($arg instanceof EmulatorObject)
				$this->class=$arg->classname;
			else
				$this->reflection=new ReflectionClass($arg);	
		else
			if ($this->emul()->user_class_exists($arg))
				$this->class=$arg;
			else
				$this->reflection=new ReflectionClass($arg);	
	}
	function _getMethods($filter=null)
	{
		$result=[];
		foreach ($this->myclass()->methods as $method)
		{
			if ($filter!==null)
			{
				if ($filter&ReflectionMethod::IS_FINAL or $filter&ReflectionMethod::IS_ABSTRACT)
					$this->emul()->error("IS_FINAL and IS_ABSTRACT not yet supported");
				if ($filter&ReflectionMethod::IS_PUBLIC and $method->visibility!=EmulatorObject::Visibility_Public)
					continue;
				if ($filter&ReflectionMethod::IS_PRIVATE and $method->visibility!=EmulatorObject::Visibility_Private)
					continue;
				if ($filter&ReflectionMethod::IS_PROTECTED and $method->visibility!=EmulatorObject::Visibility_Protected)
					continue;
				if ($filter&ReflectionMethod::IS_STATIC and !$method->static)
					continue;
			}
			$result[]=new ReflectionMethod_mock($this->class,$method->name);
		}
		return $result;
	}
	function _getProperty($name)
	{
		if (!array_key_exists($name,$this->myclass()->properties))
			throw new ReflectionException;
		return new ReflectionProperty_mock($this->class,$name);

	}
	function _getInterfaceNames()
	{
		return $this->myclass()->interfaces;
	}


}