<?php

#TODO: isset returns false on null values. Replace with array_key_exists everywhere
#major TODO: do not use recursive function calls in emulator, instead have stacks of operations and have a central 
#	function that loops over them and executes them. That way state can be saved and termination and other conditions are easy to control.

require_once __DIR__."/PHP-Parser/lib/bootstrap.php";
use PhpParser\Node;

require_once "emulator-variables.php";
require_once "emulator-functions.php";
require_once "emulator-errors.php";
require_once "emulator-expression.php";
require_once "emulator-statement.php";

/**
 * Holds an execution context, used when switching context
 */
class EmulatorExecutionContext
{
	function __construct($arr=[])
	{
		foreach ($arr as $k=>&$v)
		// {
			// if (property_exists($this, $k))
				$this->{$k}=&$v;
			// else
				// throw new \Exception("'{$k}' is not a context variable.");
		// }
	}
	// //available for everything, even includes
	// public $file;
	// public $line;

	// public $namespace;
	// public $active_namespaces;

	// //only available for functions
	// public $function;

	// //only available for [static] methods
	// public $method;
	// public $class; //dynamic class
	// public $self; //static class
	
	// //only available for bound methods
	// public $this;
}

class Emulator
{	
/**
	 * Emulator constructor
	 * init the emulator
	 */
	function __construct($init_environ=null)
	{
		$this->state=array_flip(['variables','constants','included_files'
		,'current_namespace','current_active_namespaces'
		,'current_file','current_line','current_function'
		,'output','output_buffer','functions'
		,'eval_depth','trace','output','break','continue'
		,'variable_stack'
		,'try','loop_depth','return','return_value'
		,'shutdown_functions','terminated'
		,'execution_context_stack' //all previous contexts, i.e. all current_* vars
		,'data'
		]);
		$this->parser = new PhpParser\Parser(new PhpParser\Lexer);
		$this->printer = new PhpParser\PrettyPrinter\Standard;
    	$this->init($init_environ);

	}
	use EmulatorVariables;
	use EmulatorErrors;
	use EmulatorFunctions;
	use EmulatorExpression;
	use EmulatorStatement;
	
	/**
	 * A data storage for use by mock functions and other
	 * third parties working on this emulator
	 * @var array
	 */
	public $data=[];

	public $namespaces_enabled=true;
	/**
	 * An array that holds all properties that constitute
	 * emulation state as keys.
	 * Reaedonly
	 * @var array
	 */
	public $state=[];
	/**
	 * Configuration: inifite loop limit
	 * @var integer
	 */
	public $infinite_loop	=	1000; 
	/**
	 * Maximum PHP version fully supported by the emulator
	 * this is the version that will be returned via phpversion()
	 * @var integer
	 */
	// public $max_php_version	=	"5.4.45"; 
	public $max_php_version	=	"7.0.3 "; 
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
	public $strict			= 	false;
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
	 * @state
	 * @var string
	 */
	protected $current_node,$current_statement_index;
	protected $current_function,$current_file,$current_line;
	protected $current_namespace="";
	/**
	 * Number of statements executed so far
	 * @var integer
	 */
	public $statement_count	=	0;

	/**
	 * The list of included files. used by *_once include functions as well
	 * @state
	 * @var array
	 */
	public $included_files=[];
	/**
	 * The output of the program
	 * @state
	 * @var string
	 */
	public $output;
	/**
	 * The output buffer
	 * @state
	 * @var array
	 */
	public $output_buffer=[];

	/**
	 * List of super global variables (e.g $GLOBALS, $_GET, etc.)
	 * these should be available in all contexts, i.e from all symbol tables
	 * keys are the names, values are ints
	 * @var array
	 */
	public $superglobals=[];

	/**
	 * User-defined (emulated) functions
	 * @state
	 * @var array
	 */
	public $functions=[];
	/**
	 * User-defined (emulated) constants
	 * @state
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
	 * @state
	 * @var integer
	 */
	public $eval_depth=0; 

	/**
	 * The variable stack (pushdown)
	 * On function calls and new scopes, $variables is pushed on this
	 * @state
	 * @var array
	 */
	public $variable_stack=[];
	/**
	 * Whether the application has terminated or not.
	 * Used inside the emulator to prevent further execution, e.g when die is used.
	 * @state
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
	 * @state
	 * @var array
	 */
	public $trace=[];
	/**
	 * Holds a list of execution contexts currently active (i.e. stack frames)
	 * @var array
	 */
	public $execution_context_stack=[];
	/**
	 * Number of breaks/continues
	 * Whether we still need to break or not
	 * For a normal break it becomes 1, and then back to 0 in the loop emulation code
	 * @state
	 * @var integer
	 */
	protected $break=0,$continue=0;
	/**
	 * Whether we're inside a try block or not (number of nested tries)
	 * @state
	 * @var integer
	 */
	protected $try=0;
	/**
	 * Whether we're in a loop or not (and the number of nested loops)
	 * @state
	 * @var integer
	 */
	protected $loop_depth=0;

	/**
	 * Whether return value is available
	 * @state
	 * @var boolean
	 */
	protected $return=false;
	/**
	 * The return value
	 * @state
	 * @var mixed
	 */
	protected $return_value=null;

	/**
	 * List of functions to run on shuwtdown
	 * Each element is an object of callback and args.
	 * @state
	 * @var array
	 */
	public $shutdown_functions=[]; 
	/**
	 * Retains a list of active namespaces via "use" PHP statement
	 * does not include the namespace we are in
	 * do not use directly, use active_namespaces() instead.
	 * @state
	 * @var array
	 */
	public $current_active_namespaces=[];

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

	private $isob=false; //TODO: this is part of state
	/**
	 * Temporarily disables output buffering.
	 * Used by verbose and other functions that need to generate emualtor output
	 * @return [type] [description]
	 */
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
	function init($init_environ)
	{
		$this->superglobals=array_flip(explode(",$",'_GET,$_POST,$_FILES,$_COOKIE,$_SESSION,$_SERVER,$_REQUEST,$_ENV,$GLOBALS'));
		echo str_repeat("-",80),PHP_EOL;
		if ($init_environ===null)
		{
			$init_environ=[];	
			foreach ($this->superglobals as $k=>$sg)
				if (isset($GLOBALS[$k]))
					$init_environ[$k]=&$GLOBALS[$k];
				else
					$init_environ[$k]=[];
			$init_environ['GLOBALS']=&$init_environ;
		}
		$this->variable_stack['global']=array(); //the first key in var_stack is the global scope
		$this->reference_variables_to_stack();
		foreach ($init_environ as $k=>$v)
			$this->variables[$k]=$v;
		$this->variables['GLOBALS']=&$this->variables; //as done by PHP itself
		if ($this->auto_mock)
		foreach(get_defined_functions()['internal'] as $function) //get_defined_functions gives 'internal' and 'user' subarrays.
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
		$bu=$this->terminated;
		$this->terminated=false;
		foreach ($this->shutdown_functions as $shutdown_function)
		{
			if (is_array($shutdown_function->callback))
				if ($shutdown_function->callback[0] instanceof EmulatorObject)
					$name=$shutdown_function->callback[0]->classname."->".$shutdown_function->callback[1];
				else
					$name=implode("::",$shutdown_function->callback);
			else
				$name=$shutdown_function->callback;
			$this->verbose( "Calling shutdown function: {$name}()\n");
			$this->call_function($shutdown_function->callback,$shutdown_function->args);
		}
		$this->terminated=$bu;
		$r="";
		if (count($this->output_buffer))
		{
			$this->verbose("Dumping buffered output...".PHP_EOL);		
			while (count($this->output_buffer))
				$r.=array_shift($this->output_buffer);
			$this->output($r);
		}
	}
	
	/**
	 * Outputs the args
	 * This is equal to calling echo from PHP
	 * @return [type] [description]
	 */
	function output()
	{
		$args=func_get_args();
		$data=implode("",$args);
		if (count($this->output_buffer))
			$this->output_buffer[0].=$data; #FIXME: shouldn't this be -1 instead of 0? i.e the one to the last nesting?
		else
		{
			$this->output.=$data;
			if ($this->direct_output)
				echo $data;
		}
	}
	/**
	 * Push current variables on var stack
	 */
	protected function push()
	{
		array_push($this->variable_stack,array()); //create one more symbol table
		$this->reference_variables_to_stack();
	}
	/**
	 * References $this->variables to the top of the variable_stack, instead of copying it
	 */
	private function reference_variables_to_stack()
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
		// end($this->variable_stack);
		// unset($this->variable_stack[key($this->variable_stack)]);
		$this->reference_variables_to_stack();
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
			elseif (array_key_exists($node, $this->superglobals) //super globals
				and isset($this->variable_stack['global'][$node])) //can be deleted too!
			{
				$key=$node;
				return $this->variable_stack['global'];
			}
			else
			{
				if ($create)
				{
					$key=$node;
					$this->variables[$key]=null;
					return $this->variables;
				}
				else
				{
					$this->notice("Undefined variable: {$node}");	
					return $this->null_reference($key);
				}
			}
		}
		elseif ($node instanceof Node\Scalar\Encapsed)
		{
			$key=$this->name($node);
			if ($create)
				$this->variables[$key]=null;
			return $this->variables;
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
				if ($t->dim) //explicit dimension
				{
					$ev=$this->evaluate_expression($t->dim);
					//DISCREPENCY: a literal null index evaluates to empty string rather than
					//	a null dim which means a[]=2;
					if ($ev===null) 
						$ev="";
					$indexes[]=$ev; 
				}
				else //implicit dimension
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
			if (is_string($base) and empty($indexes) and is_int($key)) //string arraydimfetch access
				return $base; //already done
			
			if (is_scalar($base)) //arraydimfetch on scalar returns null 
				return $this->null_reference($key);

			if (is_object($base) and !$base instanceof ArrayAccess)			
			{
				if ($base instanceof EmulatorObject)
					$type=$base->classname;
				else
					$type=get_class($base);
				$this->error("Cannot use object of type {$type} as array");
				return $this->null_reference($key);
			}
			// if (is_null($base))
			// 	return $this->null_reference($key);
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
			if ($create) 
				if ($key===null) #$a[...][...][]=x //add mode
				{
					$base[]=null;
					end($base);
					$key=key($base);
				}
				elseif (!isset($base[$key])) //non-existent index
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
	 * Resolves symbol name
	 * e.g function calls, variables, etc.
	 * @param  Node $ast 
	 * @return string      name
	 */
	protected function name($ast)
	{
		/**
		 * namespaced names have a name that has an array of parts.
		 * however, if it is FullyQualified (Node\Name\FullyQualified)
		 * it will only have parts 
		 */

		if (is_string($ast))
			return $ast;
		elseif ($ast instanceof Node\Expr\FuncCall)
		{
			if (is_string($ast->name) or $ast->name instanceof Node\Name)
				return $this->name($ast->name);
			else 
				return $this->evaluate_expression($ast->name);
		}
		elseif ($ast instanceof Node\Scalar\Encapsed)
		{
			$res="";
			foreach ($ast->parts as $part)
				if (is_string($part))
					$res.=$part;
				else
					$res.=$this->evaluate_expression($part);
			return $res;
		}
		elseif ($ast instanceof Node\Scalar)
			return $ast->value;
		elseif ($ast instanceof Node\Param)
			return $ast->name;
		elseif ($ast instanceof Node\Stmt\Namespace_ 
			)
		{
			if ($ast->name===null)
				return "";
			else
				return $this->name($ast->name);
		}
		// elseif ($ast instanceof Node\Name\FullyQualified)
		// {
		// 	return "\\".implode("\\",$ast->parts);
		// }
		// elseif ($ast instanceof Node\Name\Relative)
		// {
		// 	return $this->fully_qualify_name(implode("\\",$ast->parts));
		// }
		elseif ($ast instanceof Node\Name)
		{
			//compound name (of any kind), e.g variable, function, class
			$res=[];
			foreach ($ast->parts as $part)
			{
				if (is_string($part))
					$res[]=$part;
				else
					$res[]=$this->evaluate_expression($part);
			}
			return implode("\\",$res);
		}
		elseif ($ast instanceof Node\Expr\Variable)
			return $this->evaluate_expression($ast);
		elseif ($ast instanceof Node\Expr) //name can be any expr..., or can it?
			return $this->evaluate_expression($ast);
		else
			$this->error("Can not determine name: ",$ast);
	}
	/**
	 * Used on names that can be a namespace
	 * @param  Node|String $node 
	 * @return string
	 */
	function namespaced_name($node)
	{
		if (is_string($node))
			return $this->fully_qualify_name($node);
		elseif ($node instanceof Node\Name\FullyQualified)
			return implode("\\",$node->parts);
		elseif ($node instanceof Node\Name) // or $node instanceof Node\Name\Relative)
			return $this->fully_qualify_name(implode("\\",$node->parts));
		else
			return $this->name($node);
	}
	/**
	 * Returns the fully qualified namespace name associated with a relative/full/base name
	 * A fully qualified namespace starts with \
	 * @param  string $name name, can be either simple or relative or fully qualified namespaced name
	 * @return string
	 */
	private function fully_qualify_name($name)
	{
		if (!$this->namespaces_enabled) 
			return $name; 
		// if ($name[0]=="\\") 
		// {
		// 	// $this->notice("FQ called on an already FQ name!")	;
		// 	return $name; //fully qualified
		// }
		$this->verbose("Resolving relative name '{$name}'...\n",5);
		// $this->verbose("Resolving relative name '{$name}' to fully qualified name...\n",5);
		$parts=explode("\\",$name);
		if (!isset($this->current_active_namespaces[strtolower($parts[0])])) //no alias
			return $this->current_namespace($name);
		$parts[0]=$this->current_active_namespaces[strtolower($parts[0])];
		return "".implode("\\",$parts);
	}
	/**
	 * Namespaces quirk:
	 * There are 3 types of names, normal, Fully Qualified (starting with \) and Relative (possibly having \).
	 * The latter 2 are automatically resolved by name() function.
	 * Only the first can be used in definitions, but all three can be used in referencing a symbol (class, function, const)
	 * However, the important thing is that a normal name can be a relative name.
	 * For example:
	 * namespace X1\X2 {
	 * 	class X{};
	 * }
	 * namespace {
	 * 	use X1\X2\X;
	 * 	$o=new X; 
	 * }
	 *
	 * X in the last line is a normal name, but is a relative namespace and needs to be resolved.
	 * For classes, call to fully_qualify_name is forced.
	 * For constants and functions, first current_namespace+name is checked, then name itself (name is always resolved).
	 * For classes, only name is checked.
	 */
	/**
	 * Returns a name in the current namespace
	 * @param  string $name symbol
	 * @return string symbol in namespace
	 */
	function current_namespace($name)
	{
		if (!$this->namespaces_enabled) return $name;
		if ($this->current_namespace)
			return "".$this->current_namespace."\\".$name;
		else
			return "".$name;
	}
	/**
	 * Runs a PHP file
	 * Basically it sets up current file and other state variables, reads the file,
	 * parses it and passes the AST to run_code
	 * @param  string $file 
	 * @return mixed    
	 */
	public function run_file($file)
	{
		// $last_file=$this->current_file;
		$realfile=realpath($file);
		foreach (explode(":",get_include_path()) as $path)
		{
			if (file_exists($path."/{$file}"))
			{
				$realfile=realpath($path."/{$file}");
				break;
			}
		}
		// $this->current_file=$realfile;
		$tfolder=dirname($realfile)."/";
		if (strlen($this->folder)>strlen($tfolder))
			if (substr($this->folder,0,strlen($tfolder))===$tfolder)
				$this->folder=$tfolder;
			else
				$this->folder=dirname($tfolder);

		//resetting namespace
		$context=new EmulatorExecutionContext;
		$context->namespace="";
		$context->active_namespaces=[];
		$context->file=$realfile;
		$context->line=1;
		$this->context_switch($context);
		$this->verbose(sprintf("Now running %s...\n",substr($this->current_file,strlen($this->folder)) ));
		
		$this->included_files[$this->current_file]=true;

		$ast=$this->parse($file);
		$res=$this->run_code($ast);
		$this->verbose(substr($this->current_file,strlen($this->folder))." finished.".PHP_EOL,2);
		$this->context_restore();
		
		if ($this->return)
			$this->return=false;
		// $this->current_file=$last_file;
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
		$this->folder=dirname($this->entry_file);
		chdir(dirname($this->entry_file));
		// $file=basename($this->entry_file);
		ini_set("memory_limit",-1);
		set_error_handler(array($this,"error_handler"));
		// set_exception_handler(array($this,"exception_handler")); //exception handlers can not return. they terminate the program.
		$res=$this->run_file($this->entry_file);
		$this->shutdown();
		// restore_exception_handler();
		restore_error_handler();
		chdir($this->original_dir);
		return $res;
	}

	
	/**
	 * Extracts declarations in AST node
	 * Constants and function definitions are extracted before code is executed
	 * @param  Node $node 
	 */
	protected function get_declarations($node)
	{
		if (0);
		elseif ($node instanceof Node\Stmt\Namespace_)
		{
			if (!$this->namespaces_enabled) 
				$this->error("Namespace support is disabled. Please enabled it in the emulator and rerun");
			$this->current_namespace=$this->name($node);
			$this->verbose("Extracting declarations of namespace '{$this->current_namespace}'...\n",2);
			foreach ($node->stmts as $stmt)
				$this->get_declarations($stmt);
			$this->current_namespace="";

		}
		elseif ($node instanceof Node\Stmt\Use_)
		{
			if ($node->type!==1)
				#TODO:
				$this->error("'use function/const' is not yet supported. Only 'use namespace' supported so far.");
			foreach ($node->uses as $use)
			{
				$alias=$use->alias;
				$name=$this->name($use->name);
				$this->verbose("Aliasing namespace '{$name}' to '{$alias}'.\n",3);
				if (array_key_exists(strtolower($alias),$this->current_active_namespaces))
					$this->error("Cannot use {$name} as {$alias} because the name is already in use");
				$this->current_active_namespaces[strtolower($alias)]=$name;
			}
		}		
		elseif ($node instanceof Node\Stmt\Function_)
		{
			$name=$this->current_namespace($this->name($node->name));
			$index=strtolower($name);
			$context=new EmulatorExecutionContext(['function'=>$name,'file'=>$this->current_file,'namespace'=>$this->current_namespace,'active_namespaces'=>$this->current_active_namespaces]);
			$this->functions[$index]=(object)array("params"=>$node->params,"code"=>$node->stmts,'context'=>$context,'statics'=>[]); 
				
		}

	}
	/**
	 * Returns only file name of a full path, for messaging
	 * @param  string $file 
	 * @return string
	 */
	private function filename_only($file=null)
	{
		if ($file===null)
			$file=$this->current_file;
		return substr($file,strlen($this->folder));
	}
	/**
	 * Runs an AST as code.
	 * It basically loops over statements and runs them.
	 * @param  Node $ast 
	 */
	public function run_code($ast)
	{
		//first pass, get all definitions
		foreach ($ast as $node)
			$this->get_declarations($node);


		//second pass, execute
		foreach ($ast as $index=>$node)
		{
			$this->current_statement_index=$index;

			if ($node->getLine()!=$this->current_line)
			{
				$this->current_line=$node->getLine();
				if ($this->verbose) 
					$this->verbose(sprintf("%s:%d\n",$this->filename_only(),$this->current_line),3);
			}
			$this->statement_count++;
			try 
			{
				$this->run_statement($node);
			}
			catch (Exception $e)
			{
				$this->throw_exception($e);
			}
			catch (Error $e) //php 7. fortunately, even though Error is not a class, this will not err in PHP 5
			{
				// throw $e;
				// $this->throw_exception($e); //should be throw_error, throw_exception relies on type
				$this->exception_handler($e); 
			}			
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
			if (!file_exists(__DIR__."/cache")) @mkdir(__DIR__."/cache");
			if (is_writable(__DIR__."/cache"))
				file_put_contents($cache_file,gzcompress(serialize($ast)));
			else
				$this->verbose("WARNING: Can not write to cache folder, caching disabled (performance degradation).\n",0);
		}
		return $ast;
	}
	
	/**
	 * Converts an AST to printable PHP code.
	 * @param  Node|Array $ast 
	 * @return string
	 */
	function print_ast($ast)
	{
		if (!is_array($ast))
			$ast=[$ast];
		return $this->printer->prettyPrint($ast);
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
unset($mock);

if (isset($argv) and $argv[0]==__FILE__)
	die("Should not run this directly.".PHP_EOL);