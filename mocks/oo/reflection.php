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
		$this->emul()->error(__CLASS__."::{$name}() is not yet implemented ");
	}
}
class ReflectionProperty_mock extends BaseReflection_mock
{
	protected $prop,$class;
	function &class()
	{
		return $this->emul()->classes[strtolower($this->class)];

	}
	function isPrivate()
	{
		var_dump($this->class()->property_visibilities[$this->prop]);
		return $this->class()->property_visibilities[$this->prop]==EmulatorObject::Visibility_Private;
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
	function &class()
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
				// return parent::__construct($arg);
		else
			if ($this->emul()->user_class_exists($arg))
				$class=$arg;
			else
				$this->reflection=new ReflectionClass($arg);	
				// return parent::__construct($arg);
	}
	function getProperty($name)
	{
		if (!array_key_exists($name,$this->class()->properties))
			throw new ReflectionException;
		return new ReflectionProperty_mock($this->class,$name);

	}


}