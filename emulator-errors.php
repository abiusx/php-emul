<?php
#FIXME: call_user_func* should not be in backtrace
use PhpParser\Node;

class EmulatedException extends Exception {
	public $object=null;
	function __construct(EmulatorObject $e)
	{
		$this->object=$e;
	}


}
trait EmulatorErrors
{
	/**
	 * The emulator throw.
	 * If a try/catch is active, will throw, otherwise will use exception handler
	 * @param  Exception/Error $e 
	 */
	function throw_exception($e)
	{
		$class=get_class($e);
		$this->verbose("Throwing '{$class}' at {$this->filename_only()}:{$this->current_line} (try depth: {$this->try})...\n",4);
		if (!$e instanceof Exception)
		{
			$this->verbose("Exception is user-type, wrapping into EmulatedException...\n",5);
			if ($this->is_a($e,"Exception"))
				$e=new EmulatedException($e);
			else
				$this->error("Inconsistency: exception of type unrelated to Exception found");
		}
		if ($this->try>0)
			throw $e;
		else
			$this->exception_handler($e);
	}
	/**
	 * The depth of error suppression
	 * @var integer
	 */
	protected $error_suppression=0;
	/**
	 * Suppress errors one more level
	 */
	function error_silence()
	{
		$this->error_suppression++;
	}
	/**
	 * Remove error suppression
	 */
	function error_restore()
	{
		$this->error_suppression--;
	}

	public $exception_handlers=[];
	/**
	 * Default emulator exception handler
	 * @param  Exception $e 
	 * @return        [description]
	 */
	public function exception_handler($e)
	{
		if (count($this->exception_handlers))
		{
			$this->call_function(end($this->exception_handlers),[$e]);
			$this->terminated=true;	
			return true;
		}
		// return $this->error_handler($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
		//program output
		$this->output("PHP Fatal error: Uncaught Error: ".$e->getMessage()," in ",$this->current_file,":",$this->current_line,PHP_EOL);
		$this->output("Stack trace:\n");
		$backtrace=$this->print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$this->output($backtrace);
		$count=count($this->trace);
		$this->output("#",$count," {main}",PHP_EOL);
		$this->output("  thrown in ",$this->current_file," on line ",$this->current_line,PHP_EOL);
		
		$this->termination_value=-1;
		$this->terminated=true;

		//emulator output
		// $this->verbose("PHP-Emul Fatal Error: Uncaught ".$e,0); 
		$this->verbose("PHP-Emul Fatal error: Uncaught Error: ".$e->getMessage()." in ".$e->getFile().":".$e->getLine()."\n",0); 
		if ($this->verbose>=2)
		{
			$this->verbose("Emulator Backtrace:\n");
			echo $e->getTraceAsString(),PHP_EOL;
			$this->verbose("Emulation Backtrace:\n");
			echo $this->print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		}
		
		return true;
	}
	/**
	 * Compatible with PHP
	 * @return true
	 */
	public function restore_exception_handler()
	{
		if (count($this->exception_handlers))
			array_pop($this->exception_handlers);
		return true;
	}
	/**
	 * Compatible with PHP
	 * @param mixed $handler
	 */
	public function set_exception_handler($handler)
	{
		if (count($this->exception_handlers))
			$res=end($this->exception_handlers);
		else
			$res=null;

		if (!$this->is_callable($handler)) return null;
		$this->exception_handlers[]=$handler;
		return $res;
	}
	/**
	 * Retains error_reporting value
	 * @var integer
	 */
	protected $error_reporting=-1;
	/**
	 * Same as PHP's error_reporting
	 * @param  int $level 
	 * @return int
	 */
	public function error_reporting($level=null)
	{
		if ($level===null)
			return $this->error_reporting;
		$r=$this->error_reporting;
		$this->error_reporting=$level;
		return $r;
	}
	/**
	 * Used to output args of debug_print_backtrace
	 * @param  [type] $obj [description]
	 * @return [type]      [description]
	 */
	private function object_to_array($obj) 
	{
	    if(is_object($obj)) 
	    	$new = (array) $obj;
	    elseif(is_array($obj)) 
	    {
	        $new = array();
	        foreach($obj as $key => $val) 
	            $new[$key] = $this->object_to_array($val);
	    }
	    else $new = $obj;
	    return $new;       
	}
	/**
	 * Returns backtrace equal to that of debug_backtrace
	 * @param  int  $options 
	 * @param  integer $limit   
	 * @return array
	 */
	function backtrace($options=DEBUG_BACKTRACE_PROVIDE_OBJECT,$limit=0)
	{
		#TODO; possible options values : DEBUG_BACKTRACE_PROVIDE_OBJECT, DEBUG_BACKTRACE_IGNORE_ARGS
		#TODO: this returns EmulatorObject
		$t=$this->trace;
		while (count($t) and (end($t)->function=="debug_backtrace" or end($t)->function=="debug_print_backtrace") )
			array_pop($t);
		$t=array_reverse($t);
		if (! ($options&DEBUG_BACKTRACE_PROVIDE_OBJECT))
			return $this->object_to_array($t);
		return $t;
	}
	/**
	 * Prints the backtrace equal to debug_print_backtrace
	 * This function does not actually print the backtrace, it just returns it as a string.
	 * @param  int  $options 
	 * @param  integer $limit   
	 * @return string           backtrace print.
	 */
	function print_backtrace($options=DEBUG_BACKTRACE_PROVIDE_OBJECT ,$limit=0)
	{
		$noArgs=($options&DEBUG_BACKTRACE_IGNORE_ARGS);
		$trace=$this->backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,$limit);
		$count=count($trace);
		$out="";
		for ($i=0;$i<$count;++$i)
		{
			$t=$trace[$i];
			$function=$args=$file=$line=$class="";
			if (isset($t->function))
				$function=$t->function;
			if (isset($t->class))
				$class=$t->class;
			if ($t->type) //not function, method or static-method
				$function=$class.$t->type.$function;
			if (isset($t->file))
				$file=$t->file;
			if (isset($t->line))
				$line=$t->line;
			if ( isset($t->args))
				if (!$noArgs or $function=="require_once" or $function=="include_once" or $function=="include" or $function=="require")
					$args=implode(", ",array_map(
						function ($x){ 
							$t=gettype($x);
							if ($t=="boolean" or $t=="integer" or $t=="duoble" or $t=="string")
								return $x;
							else 
								return $t;
						}
						,$t->args));

			$out.=sprintf ("#%d %s(%s) called at [%s:%d]\n",$i,$function,$args,$file,$line);
		}
		return $out;

	}
	
	public $error_handlers=[];
	/**
	 * Equivalent to PHP's set_error_handler
	 * @param callable $handler         
	 * @param int $error_reporting 
	 * @return  mixed
	 */
	function set_error_handler($handler,$error_reporting=32767)
	{
		if (count($this->error_handlers))
			$res=end($this->error_handlers)['handler'];
		else
			$res=null;

		if (!$this->is_callable($handler)) return null;
		$this->error_handlers[]=['handler'=>$handler,'error_reporting'=>$error_reporting];
		return $res;
	}
	/**
	 * Same as PHP's restore_error_handler
	 * @return true
	 */
	function restore_error_handler()
	{
		if (count($this->error_handlers))
			array_pop($this->error_handlers);
		return true;
	}
	/**
	 * The emulator error handler (in case an error happens in the emulation, that is not intended/caught/handled)
	 * @param  [type] $errno   [description]
	 * @param  [type] $errstr  [description]
	 * @param  [type] $errfile [description]
	 * @param  [type] $errline [description]
	 * @return [type]          [description]
	 */
	function error_handler($errno, $errstr, $errfile, $errline)
	{
		if (count($this->error_handlers) and $errno&end($this->error_handlers)['error_reporting'])
			if (false!==$this->call_function(end($this->error_handlers)['handler'],func_get_args())) return true;
		$this->stash_ob();
		$file=$errfile;
		$line=$errline;
		$file2=$line2=null;
		if (isset($this->current_file)) $file2=$this->current_file;
		if (isset($this->current_node)) $line2=$this->current_node->getLine();
		$fatal=false;
		switch($errno) //http://php.net/manual/en/errorfunc.constants.php
		{
			case E_USER_NOTICE:
			case E_NOTICE:
				$str="Notice";
				break;
			case E_ERROR:
			case E_USER_ERROR:
				$fatal=true;
				$str="Error";
				break;
			case E_USER_WARNING:
			case E_WARNING:
				$str="Warning";
				break;
			default:
				$str="Error?";
		}
		if ($fatal)
			$fatal_str="Fatal ";
		else
			$fatal_str="";
		$this->verbose("PHP-Emul {$str}:  {$errstr} in {$file} on line {$line} ($file2:$line2)".PHP_EOL,0);
		$this->output("PHP {$fatal_str}{$str}:  {$errstr} in {$file2} on line {$line2}".PHP_EOL);
		if ($fatal or $this->strict) 
		{
			$this->terminated=true;
			$this->termination_value=-1;
			if ($this->verbose>=2)
			{
				$this->verbose("Emulator Backtrace:\n");
				debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
				$this->verbose("Emulation Backtrace:\n");
				echo $this->print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			}
		}
		$this->restore_ob();
		return true;
	}
	function todo()
	{
		return call_user_func_array([$this,"error"], func_get_args());
	}
	/**
	 * Used by emulator to mark emulation errors
	 * @param  [type] $msg  [description]
	 * @param  [type] $node [description]
	 * @return [type]       [description]
	 */
	public function error($msg,$node=null)
	{
		$this->verbose("Emulation Error: ",0);
		$this->_error($msg,$node);
		$this->terminated=true;
		return null;
	}
	/**
	 * Core function used by all types of error (warning, error, notice, etc.)
	 * @param  [type]  $msg     [description]
	 * @param  [type]  $node    [description]
	 * @param  boolean $details [description]
	 * @return [type]           [description]
	 */
	protected function _error($msg,$node=null,$details=true)
	{
		#TODO: these should be handled by error_handler as well, and the program.
		$this->verbose($msg." in ".$this->current_file." on line ".$this->current_line.PHP_EOL,0);
		// $this->output($msg." in ".$this->current_file." on line ".$this->current_line.PHP_EOL);
		if ($details)
		{
			print_r($node);
			if ($this->verbose>=2)
			{
				$this->verbose("Emulator Backtrace:\n");
				debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
				$this->verbose("Emulation Backtrace:\n");
				echo $this->print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			}
		}
		if ($this->strict) 
		{
			$this->terminated=true;
			$this->termination_value=-2;
		}


	}
	/**
	 * Notices 
	 * @param  [type] $msg  [description]
	 * @param  [type] $node [description]
	 * @return [type]       [description]
	 */
	public function notice($msg,$node=null)
	{
		if ($this->error_suppression) return false;
		if ($this->error_reporting & E_NOTICE or (defined("E_USER_NOTICE") and $this->error_reporting & E_USER_NOTICE))
		{
			$this->verbose("Emulation Notice: ",0);
			$this->_error($msg,$node,false or $this->strict);
			return true;
		}
		return false;
	}
	/**
	 * Warnings
	 * @param  [type] $msg  [description]
	 * @param  [type] $node [description]
	 * @return [type]       [description]
	 */
	public function warning($msg,$node=null)
	{
		if ($this->error_suppression) return false;
		if ($this->error_reporting & E_WARNING or (defined("E_USER_WARNING") and $this->error_reporting & E_USER_WARNING))
		{
			$this->verbose("Emulation Warning: ",0);
			$this->_error($msg,$node);
			return true;
		}
		return false;
	}
}
