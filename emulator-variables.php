<?php
use PhpParser\Node;
trait EmulatorVariables
{
	/**
	 * Symbol table of the current scope (all variables)
	 * @var array
	 */
	public $variables=[]; 
	/**
	 * Function used to return something when reference returning 
	 * functions fail and have to return something.
	 * Can set an input variable to null for ease too.
	 * @var null
	 */
	protected function &null_reference(&$var=null)
	{
		$var=null;
		// unset($this->null_reference);
		$this->null_reference=null;
		return $this->null_reference;

	}
	/**
	 * Set a value to a variable
	 * creates if not exists
	 * @param  Node $node  
	 * @param  mixed $value 
	 * @return mixed or null        
	 */
	function variable_set($node,$value=null)
	{
		$r=&$this->symbol_table($node,$key,true);
		if ($key!==null)
			return $r[$key]=$value;
		else 
			return null;
	}
	function variable_set_byref($node,&$ref)
	{
		$r=&$this->symbol_table($node,$key,true);
		if ($key!==null)
			return $r[$key]=&$ref;
		else 
			return null;
	}
	/**
	 * Get the value of a variable
	 * @param  Node $node 
	 * @return mixed       
	 */
	function variable_get($node)
	{
		$r=&$this->symbol_table($node,$key,false);
		if ($key!==null)
			if (is_string($r))
				return $r[$key];
			elseif (is_null($r)) //any access on null is null [https://bugs.php.net/bug.php?id=72786]
				return null;
			elseif (is_array($r)) //support for iterable objects
			{
				if (!array_key_exists($key, $r)) //only works for arrays, not strings
				{
					$this->notice("Undefined index: {$key}");
					return null;
				}
				return $r[$key];
			}
			// elseif (is_object($r))
			// {
			// 	if ($r instanceof ArrayAccess)
			// 		return $r[$key];
			// 	else
			// 	{
			// 		if ($r instanceof EmulatorObject)
			// 			$type=$r->classname;		
			// 		else
			// 			$type=get_class($r);
			// 		$this->error("Cannot use object of type {$type} as array");
			// 		return null;
			// 	}
			// }
			else
			{
				$this->warning("Using unknown type as array");
				return $r[$key];
			}
		return null;
	}
	/**
	 * Check whether or not a variable exists
	 * @param  Node $node 
	 * @return bool       
	 */
	function variable_isset($node)
	{
		$this->error_silence();
		$r=$this->symbol_table($node,$key,false);
		$this->error_restore();
		return $key!==null and isset($r[$key]);
	}
	/**
	 * Deletes a variable
	 * @param  Node $node 
	 */
	function variable_unset($node)
	{
		$base=&$this->symbol_table($node,$key,false);
		if ($key!==null)
			unset($base[$key]);
	}
	/**
	 * Returns reference to a variable
	 * minimize uses of this, as it's very hard to behave properly when a variable does not exist
	 * @param  Node $node 
	 * @return reference
	 */
	function &variable_reference($node,&$success=null)
	{
		$r=&$this->symbol_table($node,$key,false); //this should NOT always create, e.g. static property fetch
		//in fact it should never create, anywhere its needed, it is explicitly created by variable_set
		if ($key===null) //not found or GLOBALS
		{
			$success=false;
			return $this->null_reference();
		}
		elseif (is_array($r))
		{
			$success=true;	
			return $r[$key]; //if $r[$key] does not exist, will be created in byref use.
		}
		else
		{
			$success=false;	
			$this->error("Could not retrieve reference",$node);
		}
	}

}