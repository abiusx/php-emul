<?php

class ReflectionClass_mock
{
	public $reflection=null;
	static public $emul;
	// public $emulated=false;
	protected $class="";
	function emul()
	{
		return self::$emul;
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
	function __call($name,$args)
	{
		if ($this->reflection)
			return call_user_func_array(array($this->reflection,$name), $args);




		$this->emul()->error(__CLASS__."::{$name} is not yet implemented ");

	}


}