<?php

trait EmulatorErrors
{
	private function object_to_array($obj) 
	{
	    if(is_object($obj)) $obj = (array) $obj;
	    if(is_array($obj)) 
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
		while (count($t) and end($t)->function=="debug_backtrace" or end($t)->function=="debug_print_backtrace")
			array_pop($t);
		$t=array_reverse($t);
		if (! ($options&DEBUG_BACKTRACE_PROVIDE_OBJECT))
			return $this->object_to_array($t);
		return $t;
	}
	/**
	 * Prints the backtrace equal to debug_print_backtrace
	 * @param  int  $options 
	 * @param  integer $limit   
	 * @return [type]           [description]
	 */
	function print_backtrace($options=DEBUG_BACKTRACE_PROVIDE_OBJECT ,$limit=0)
	{
		$noArgs=($options&DEBUG_BACKTRACE_IGNORE_ARGS);
		$trace=$this->backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,$limit);
		$count=count($trace);
		for ($i=0;$i<$count;++$i)
		{
			$t=$trace[$i];
			$function=$args=$file=$line="";
			if (isset($t->function))
				$function=$t->function;
			if (isset($t->type))
				$function=$t->class.$t->type.$function;
			if (isset($t->file))
				$file=$t->file;
			if (isset($t->line))
				$line=$t->line;
			if ( isset($t->args))
				if (!$noArgs or $function=="require_once" or $function=="include_once" or $function=="include" or $function=="require")
					@$args=implode(", ",$t->args);

			printf ("#%d %s(%s) called at [%s:%d]\n",$i,$function,$args,$file,$line);
		}

	}
	/**
	 * The emulator error handler (in case an error happens in the emulation, that is not intended)
	 * @param  [type] $errno   [description]
	 * @param  [type] $errstr  [description]
	 * @param  [type] $errfile [description]
	 * @param  [type] $errline [description]
	 * @return [type]          [description]
	 */
	function error_handler($errno, $errstr, $errfile, $errline)
	{
		$file=$errfile;
		$line=$errline;
		$file2=$line2=null;
		if (isset($this->current_file)) $file2=$this->current_file;
		if (isset($this->current_node)) $line2=$this->current_node->getLine();
		$fatal=false;
		switch($errno) //http://php.net/manual/en/errorfunc.constants.php
		{
			case E_ERROR:
				$fatal=true;
				$str="Error";
				break;
			case E_WARNING:
				$str="Warning";
				break;
			default:
				$str="Error";
		}

		$this->verbose("PHP-Emul {$str}:  {$errstr} in {$file} on line {$line} ($file2:$line2)".PHP_EOL,0);
		if ($fatal or $this->strict) 
		{
			$this->terminated=true;
			if ($this->verbose>=2)
			{
				$this->verbose("Emulator Backtrace:\n");
				debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
				$this->verbose("Emulation Backtrace:\n");
				$this->print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			}
		}
		return true;
	}
	/**
	 * Used by emulator to mark emulation errors
	 * @param  [type] $msg  [description]
	 * @param  [type] $node [description]
	 * @return [type]       [description]
	 */
	protected function error($msg,$node=null)
	{
		$this->verbose("Emulation Error: ",0);
		$this->_error($msg,$node);
		$this->terminated=true;
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
		$this->verbose($msg." in ".$this->current_file." on line ".$this->current_line.PHP_EOL,0);
		if ($details)
		{
			print_r($node);
			if ($this->verbose>=2)
			{
				$this->verbose("Emulator Backtrace:\n");
				debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
				$this->verbose("Emulation Backtrace:\n");
				$this->print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			}
		}
		if ($this->strict) $this->terminated=true;
	}
	/**
	 * Notices 
	 * @param  [type] $msg  [description]
	 * @param  [type] $node [description]
	 * @return [type]       [description]
	 */
	protected function notice($msg,$node=null)
	{
		if ($this->error_suppression) return false;
		$this->verbose("Emulation Notice: ",0);
		$this->_error($msg,$node,false or $this->strict);
	}
	/**
	 * Warnings
	 * @param  [type] $msg  [description]
	 * @param  [type] $node [description]
	 * @return [type]       [description]
	 */
	protected function warning($msg,$node=null)
	{
		if ($this->error_suppression) return false;
		$this->verbose("Emulation Warning: ",0);
		$this->_error($msg,$node);
		// trigger_error($msg);
	}
}
