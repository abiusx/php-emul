<?php

#major TODO: do not use function calls in emulator, instead have stacks of operations and have a central 
#	function that loops over them and executes them. That way state can be saved and termination and other conditions are easy to control.
#TODO: make symbol_table return the actual variable instead of superset, and handle unset separately. 
#	This is making things too complicated. (i.e, replace symbol_table with variable_get, variable_set and variable_reference functions)

require_once __DIR__."/PHP-Parser/lib/bootstrap.php";
use PhpParser\Node;

require_once "emulator-variables.php";
require_once "emulator-functions.php";
require_once "emulator-errors.php";
require_once "emulator-expression.php";
require_once "emulator-statement.php";

class Emulator
{	

	use EmulatorVariables;
	use EmulatorErrors;
	use EmulatorFunctions;
	use EmulatorExpression;
	use EmulatorStatement;
	/**
	 * Configuration: inifite loop limit
	 * @var integer
	 */
	public $infinite_loop	=	100; 
	/**
	 * Configuration: whether to output directly, or just store it in $output
	 * @var boolean
	 */
	public $direct_output	=	false;
	/**
	 * Configuration: Verbose messaging depth. -1 means no messages, even critical ones
	 * @var integer
	 */
	public $verbose			=	1;

	/**
	 * Whether to stop on all errors or not.
	 * @var boolean
	 */
	public $strict			= 	true;
	/**
	 * Whether to automatically mock functions or not
	 * If true, on init emulator will mock all internal php functions with their mocked version.
	 * If there's a mocked function (e.g array_walk_mock), emulator will use that instead of the original function
	 * Some functions need to be mocked for the emulator to work properly. These are placed in 'mocks/' folder
	 * @var boolean
	 */
	public $auto_mock		=	true;

	/**
	 * Emulator current settings
	 * mostly used for error reporting
	 * @var string
	 */
	protected $current_node,$current_file,$current_line;
	protected $current_function,$current_statement_index;
	
	/**
	 * Number of statements executed so far
	 * @var integer
	 */
	public $statement_count	=	0;

	/**
	 * The list of included files. used by *_once include functions as well
	 * @var array
	 */
	public $included_files=[];
	/**
	 * The output of the program
	 * @var string
	 */
	public $output;
	/**
	 * The output buffer
	 * @var array
	 */
	public $output_buffer=[];

	/**
	 * Super global variables (e.g $GLOBALS, $_GET, etc.)
	 * @var array
	 */
	public $super_globals=[];

	/**
	 * User-defined (emulated) functions
	 * @var array
	 */
	public $functions=[];
	/**
	 * User-defined (emulated) constants
	 * @var array
	 */
	public $constants=[];

	/**
	 * The parser object used by the emulator. 
	 * @var [type]
	 */
	public $parser;
	
	/**
	 * The depth of eval.
	 * Everything eval is used, this is incremented. Allows us to know whether we're inside eval'd code or not.
	 * @var integer
	 */
	public $eval_depth=0; 

	/**
	 * The variable stack (pushdown)
	 * On function calls and new scopes, $variables is pushed on this
	 * @var array
	 */
	public $variable_stack=[];
	/**
	 * Whether the application has terminated or not.
	 * Used inside the emulator to prevent further execution, e.g when die is used.
	 * @var boolean
	 */
	public $terminated=false;

	/**
	 * List of mocked functions. 
	 * Keys are original functions, values are mocked equivalents
	 * @var array
	 */
	public $mock_functions=[];

	/**
	 * Stack trace.
	 * Used to see if we're inside a function or a method or etc.
	 * Obeys the structure of debug_backtrace()
	 * @var array
	 */
	public $trace=[];

	/**
	 * Number of breaks/continues
	 * Whether we still need to break or not
	 * For a normal break it becomes 1, and then back to 0 in the loop emulation code
	 * @var integer
	 */
	protected $break=0,$continue=0;
	/**
	 * Whether we're inside a try block or not (number of nested tries)
	 * @var integer
	 */
	protected $try=0;
	/**
	 * Whether we're in a loop or not (and the number of nested loops)
	 * @var integer
	 */
	protected $loop_depth=0;

	/**
	 * Whether return value is available
	 * @var boolean
	 */
	protected $return=false;
	/**
	 * The return value
	 * @var mixed
	 */
	protected $return_value=null;

	/**
	 * List of functions to run on shuwtdown
	 * Each element is an object of callback and args.
	 * @var array
	 */
	public $shutdown_functions=[]; 

	/**
	 * Output status messages of the emulator
	 * @param  string  $msg       
	 * @param  integer $verbosity 1 is basic messages, 0 is always shown, higher means less important
	 */
	function verbose($msg,$verbosity=1)
	{
		$this->stash_ob(); #don't really need it here, handled at a higher level
		static $lastVerbosity=1;
		static $verbosities=[0];
		$number="";
		if ($verbosity>0)
		{
			if ($verbosity>$lastVerbosity)
				for ($i=0;$i<$verbosity-$lastVerbosity;++$i)
					$verbosities[]=1;
			else
			{
				if ($verbosity<$lastVerbosity)
					for ($i=0;$i<$lastVerbosity-$verbosity;++$i)
						array_pop($verbosities);
				$verbosities[count($verbosities)-1]++;
			}
			$lastVerbosity=$verbosity;
			$number=implode(".",$verbosities);
		}

		if ($this->verbose>=$verbosity)
			echo str_repeat("---",$verbosity)." ".$number." ".$msg;
		$this->restore_ob();
	}

	private $isob=false;
	function stash_ob()
	{
		$this->isob=ob_get_level()!=0;
		if ($this->isob) $this->output(ob_get_clean());
	}
	function restore_ob()
	{
		if ($this->isob) ob_start();
	}
	/**
	 * Initialize the emulator by setting environment variables (super globals)
	 * and mocking mock functions
	 */
	function init()
	{
		$this->variable_stack['global']=array();
		$this->variables=&$this->variable_stack['global'];
		foreach ($GLOBALS as $k=>$v)
		{
			// if ($k=="GLOBALS") continue; 
			$this->super_globals[$k]=$v;
		}
		if ($this->auto_mock)
		foreach(get_defined_functions()['internal'] as $function) //get_defined_functions gives internal and user subarrays.
		{
			if (function_exists($function."_mock"))
				$this->mock_functions[strtolower($function)]=$function."_mock";
		}
	}
	/**
	 * Called after execution finished
	 * Runs shutdown functions
	 */
	protected function shutdown()
	{

		$this->verbose("Shutting down...".PHP_EOL);
		//FIXME TODO
		foreach ($this->shutdown_functions as $shutdown_function)
		{
			if (is_array($shutdown_function->callback))
				$name=implode("::",$shutdown_function->callback);
			else
				$name=$shutdown_function->callback;
			$this->verbose( "Calling shutdown function: {$name}()\n");
			// print_r($shutdown_function);
			$this->call_function($shutdown_function->callback,$shutdown_function->args);
		}
	}
	
	/**
	 * Outputs the args
	 * @return [type] [description]
	 */
	function output()
	{
		$args=func_get_args();
		$data=implode("",$args);
		$this->output.=$data;
		if ($this->direct_output)
			echo $data;
	}
	/**
	 * Push current variables on var stack
	 */
	protected function push()
	{
		array_push($this->variable_stack,array());
		$this->_reference_variables_to_stack();
	}
	/**
	 * References $this->variables to the top of the variable_stack, instead of copying it
	 */
	private function _reference_variables_to_stack()
	{
		unset($this->variables);
		end($this->variable_stack);
		$this->variables=&$this->variable_stack[key($this->variable_stack)];
	}
	/**
	 * Pop off the variable stack
	 */
	protected function pop()
	{
		array_pop($this->variable_stack);
		$this->_reference_variables_to_stack();
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


	/**
	 * Returns the base array (symbol table) that the variable exists in, as well as the key in that array for the variable
	 * 
	 * 
	 * @param  Node  $node   
	 * @param  byref  &$key  the key of the element, which will be null if not found
	 * @param  boolean $create 
	 * @return reference          reference to the symbol table array (check key first before accessing this)
	 */
	protected function &symbol_table($node,&$key,$create)
	{
		if ($node===null)
		{
			$this->notice("Undefined variable (null node).");	
			return $this->null_reference($key);
		}
		elseif (is_string($node))
		{
			if (array_key_exists($node, $this->variables))
			{
				$key=$node;	
				return $this->variables;
			}
			elseif ($node == "GLOBALS")
			{
				$key='global';	
				return $this->variable_stack;
			}
			elseif (array_key_exists($node, $this->super_globals)) //super globals
			{
				$key=$node;
				return $this->super_globals;
			}
			else
			{
				if ($create)
				{
					$this->variables[$node]=null;
					$key=$node;
					return $this->variables;
				}
				else
				{
					$this->notice("Undefined variable: {$node}");	
					return $this->null_reference($key);
				}
			}
		}
		elseif ($node instanceof Node\Expr\ArrayDimFetch)
		{
			$t=$node;

			//each ArrayDimFetch has a var and a dim. var can either be a variable, or another ArrayDimFetch
			$dim=0;
			$indexes=[];
			
			//extracting indices
			while ($t instanceof Node\Expr\ArrayDimFetch)
			{
				$dim++;
				if ($t->dim)
					$indexes[]=$this->evaluate_expression($t->dim);
				else
					$indexes[]=null;
				$t=$t->var;
			}
			$indexes=array_reverse($indexes);
			//check if the array exists at all or not
			$base=&$this->symbol_table($t,$key2,$create);
			if ($key2===null) //the base arrayDimFetch variable not exists, e.g $a in $a[1][2]
				return $this->null_reference($key);
			$base=&$base[$key2];
			$key=array_pop($indexes);
			foreach ($indexes as $index)
			{
				if ($index===NULL)
				{
					$base[]=NULL; //append to base array
					end($base);	//move the pointer to end of base array
					$index=key($base); //retrieve the key as index
				}
				elseif (!isset($base[$index]))
					if ($create)
					{
						$this->verbose("Creating array index '{$index}'...".PHP_EOL,5);	
						$base[$index]=null;
					}
					else
						return $this->null_reference($key);

				$base=&$base[$index];
			}
			if ($create and !isset($base[$key]))
				if ($key===null)
					$base[]=null;
				else
					$base[$key]=null;

			return $base;
		}
		elseif ($node instanceof Node\Expr\Variable)
		{
			return $this->symbol_table($node->name,$key,$create);
		}
		elseif ($node instanceof Node\Expr)
		{
			#TODO: temporary variable for symbol table to return... think of a workaround?
			#Fatal error: Can't use function return value in write context
			$hack=array('temp'=>$this->evaluate_expression($node))	;
			$key='temp';
			return $hack;
		}
		else
		{
			$this->error("Can not find variable reference of this node type.",$node);
			return $this->null_reference($key);
		}
	}
	/**
	 * Returns the name of nodes that have names
	 * e.g function calls, variables, etc.
	 * Used multiple times in the emulator code
	 * @param  Node $ast 
	 * @return string      name
	 */
	protected function name($ast)
	{
		if (is_string($ast))

			return $ast;
		elseif ($ast instanceof Node\Expr\FuncCall)
		{

			if (is_string($ast->name) or $ast->name instanceof Node\Name)
				return $this->name($ast->name);
			else 
				return $this->evaluate_expression($ast->name);
		}
		elseif ($ast instanceof Node\Scalar)
			return $ast->value;
		elseif ($ast instanceof Node\Param)
			return $ast->name;
		elseif ($ast instanceof Node\Name)
		{

			$res="";
			foreach ($ast->parts as $part)
			{
				if (is_string($part))
					$res.=$part;
				else
					$res.=$this->evaluate_expression($part);
			}
			return $res;
		}
		elseif ($ast instanceof Node\Expr\Variable)
			return $this->evaluate_expression($ast);
		else
			$this->error("Can not determine name: ",$ast);
	}

	
	/**
	 * Runs a PHP file
	 * Basically it sets up current file and other state variables, reads the file,
	 * parses it and passes the result to run_code
	 * @param  string $file 
	 * @return mixed    
	 */
	public function run_file($file)
	{
		$last_file=$this->current_file;
		$this->current_file=realpath($file);
		$tfolder=dirname($this->current_file)."/";
		if (!isset($this->folder) or strlen($this->folder>$tfolder))
			$this->folder=$tfolder;

		$this->verbose(sprintf("Now running %s...\n",substr($this->current_file,strlen($this->folder)) ));
		
		$this->included_files[$this->current_file]=true;
		
		$ast=$this->parse($file);

		$res=$this->run_code($ast);
		$this->verbose(substr($this->current_file,strlen($this->folder))." finished.".PHP_EOL,2);
		if ($this->return)
			$this->return=false;
		$this->current_file=$last_file;
		return $res;
	}
	/**
	 * Starts the emulation
	 * A php file should be given here
	 * Current directory and other variables are set up here
	 * @param  string  $file  
	 * @param  boolean $chdir whether to change dir to the file's location or not
	 * @return mixed         
	 */
	function start($file,$chdir=true)
	{
		$this->entry_file=realpath($file);
		if (!$this->entry_file)
		{
			$this->verbose("File not found '{$file}'.".PHP_EOL,0);
			return false;
		}
		$this->original_dir=getcwd();
		chdir(dirname($this->entry_file));
		$file=basename($this->entry_file);
		ini_set("memory_limit",-1);
		set_error_handler(array($this,"error_handler"));
		$res=$this->run_file($file);
		restore_error_handler();
		$this->shutdown();
		chdir($this->original_dir);
		return $res;
	}
	/**
	 * Used to check if loop condition is still valid
	 * @return boolean
	 */
	private function loop_condition($i=0)
	{
		if ($this->break)
		{
			$this->break--;
			return true;
		}
		if ($this->continue)
		{
			$this->continue--;
			if ($this->continue)
				return true; 
		}
		if ($i>$this->infinite_loop)
		{
			$this->error("Infinite loop");
			return true; 
		}
		if ($this->terminated)
			return true;
		return false;
	}
	
	/**
	 * Extracts declarations in AST node
	 * Constants and function definitions are extracted before code is executed
	 * @param  Node $node 
	 */
	protected function get_declarations($node)
	{
		if (0);
		elseif ($node instanceof Node\Stmt\Function_)
		{
			$name=$this->name($node->name);
			$this->functions[strtolower($name)]=(object)array("params"=>$node->params,"code"=>$node->stmts,"file"=>$this->current_file,"statics"=>[]); 
		}
		elseif ($node instanceof Node\Stmt\Const_)
		{
			foreach ($node->consts as $const)
			{
				if (isset($this->constants[$const->name]))
					$this->warning("Constant {$node->name} already defined");
				else
					$this->constants[($const->name)]=$this->evaluate_expression($const->value);
			}
		}
	}
	/**
	 * Runs an AST as code.
	 * It basically loops over statements and runs them.
	 * @param  Node $ast 
	 */
	protected function run_code($ast,$start_index=null)
	{
		//first pass, get all definitions
		if ($start_index===null)
		foreach ($ast as $node)
			$this->get_declarations($node);


		//second pass, execute
		foreach ($ast as $index=>$node)
		{
			if ($index<$start_index) 
				continue;
			$this->current_node=$node;
			$this->current_statement_index=$index;

			if ($node->getLine()!=$this->current_line)
			{
				$this->current_line=$node->getLine();
				if ($this->verbose) 
					$this->verbose(sprintf("%s:%d\n",substr($this->current_file,strlen($this->folder)),$this->current_line),3);
			}
			$this->statement_count++;
			$this->run_statement($node);
			if ($this->terminated) return null;
			if ($this->return) return $this->return_value;
			if ($this->break) break;
			if ($this->continue) break;
		}
		$this->current_statement_index=null;
	}	

	/**
	 * Returns the AST of a PHP file to emulator
	 * attempts to cache the AST and re-use
	 * @param  string $file 
	 * @return array       
	 */
	protected function parse($file)
	{
		#TODO: decide much better memory on single-file unserialize, runtime overhead is about 3-4 times
		#350 mb vs 650 mb (baseline 250 mb), 12s (8 unserialize) vs 7s (3.5 unserialize+gc) (baseline 27s)
		#maybe cache a few files? because the single file cache grows over time
		$mtime=filemtime($file);
		$md5=md5($file);
		$cache_file=__DIR__."/cache/parsetree-{$md5}-{$mtime}";
		if (file_exists($cache_file))
			$ast=unserialize(gzuncompress(file_get_contents($cache_file)));
		else
		{
			$code=file_get_contents($file);
			$ast=$this->parser->parse($code);
			file_put_contents($cache_file,gzcompress(serialize($ast)));
		}
		return $ast;
	}
	/**
	 * Emulator constructor
	 * init the emulator
	 */
	function __construct()
	{
		$this->parser = new PhpParser\Parser(new PhpParser\Lexer);
    	$this->init();
	}
	/**
	 * Emulator destructor
	 */
	function __destruct()
	{
		$this->verbose(sprintf("Memory usage: %.2fMB (%.2fMB)\n",memory_get_usage()/1024.0/1024.0,memory_get_peak_usage()/1024.0/1024.0));
	}




}
//this loads all mock functions, so that auto-mock will replace them
foreach (glob(__DIR__."/mocks/*.php") as $mock)
	require_once $mock;



if (isset($argc) and $argv[0]==__FILE__)
{
	die("Should not run this directly.".PHP_EOL);
}
