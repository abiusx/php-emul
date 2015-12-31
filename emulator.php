<?php
require_once __DIR__."/PHP-Parser/lib/bootstrap.php";
use PhpParser\Node;
#major TODO: do not use function calls in emulator, instead have stacks of operations and have a central 
#	function that loops over them and executes them. That way state can be saved and termination and other conditions are easy to control.
#TODO: PhpParser\Node\Stmt\StaticVar vs PhpParser\Node\Stmt\Static_
#TODO: use ReflectionParameter::isCallable to auto-wrap callbacks for core functions
#TODO: make symbol_table return the actual variable instead of superset, and handle unset separately. 
#	This is making things too complicated. (i.e, replace symbol_table with variable_get, variable_set and variable_reference functions)

require_once "emulator-variables.php";
require_once "emulator-functions.php";
require_once "emulator-errors.php";

class Emulator
{	
	use EmulatorVariables;
	use EmulatorErrors;
	use EmulatorFunctions;
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
		if ($this->verbose>=$verbosity)
			echo str_repeat("-",$verbosity*3)." ".$msg;
	}
	/**
	 * Initialize the emulator by setting environment variables (super globals)
	 * and mocking mock functions
	 */
	function init()
	{
		foreach ($GLOBALS as $k=>$v)
		{
			// if ($k=="GLOBALS") continue; 
			$this->super_globals[$k]=$v;
		}
		// $this->super_globals["GLOBALS"]=&$this->super_globals;
		// $this->variables['_POST']=isset($_POST)?$_POST:array();
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
			$this->verbose( "Calling shutdown function: ");
			print_r($shutdown_function);
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
	 * Evaluate all nodes of type Node\Expr and return appropriate value
	 * This is the core of the emulator/interpreter.
	 * @param  Node $ast Abstract Syntax Tree node
	 * @return mixed      value
	 */
	protected function evaluate_expression($ast)
	{
		if ($this->terminated) return null;
		$node=$ast;
		$this->current_node=$node;
		if (is_object($node) and method_exists($node, "getLine") and $node->getLine()!=$this->current_line)
		{
			$this->current_line=$node->getLine();
			$this->verbose("Line {$this->current_line} (expression)".PHP_EOL,4);
		}	
		if ($node===null)
			return null;
		elseif (is_array($node))
			$this->error("Did not expect array node!",$node);
		elseif ($node instanceof Node\Expr\FuncCall)
		{
			$name=$this->name($node);
			return $this->call_function($name,$node->args);
			
		}
		elseif ($node instanceof Node\Expr\AssignRef)
		{
			// $originalVar=$this->name($node->expr);
			$originalVar=&$this->variable_reference($node->expr);
			$this->variable_set($node->var,$originalVar);
			$var=$originalVar;
		}
		elseif ($node instanceof Node\Expr\Assign)
		{
			if ($node->var instanceof Node\Expr\List_) //list(x,y)=f()
			{
				$resArray=$this->evaluate_expression($node->expr);
				if (count($resArray)!=count($node->var->vars))
					$this->warning("list() used but number of arguments do not match");
				reset($resArray);
				$outArray=[];
				foreach ($node->var->vars as $var)
				{
					//not necessarily a variable, can be an arrayDim or objectProperty
					$outArray[]=$this->variable_set($var,current($resArray));
					// $outArray[]=$base[$key]=current($resArray);
					next($resArray);
				}
				return $outArray;
			}
			else
			{
				return $this->variable_set($node->var,$this->evaluate_expression($node->expr));
			}
		}
		elseif ($node instanceof Node\Expr\ArrayDimFetch) //access multidimensional arrays $x[...][..][...]
			return $this->variable_get($node); //should not create
		elseif ($node instanceof Node\Expr\Array_)
		{
			$out=[];
			foreach ($node->items as $item)
				if (isset($item->key))
					$out[$this->evaluate_expression($item->key)]=$this->evaluate_expression($item->value);
				else
					$out[]=$this->evaluate_expression($item->value);
			return $out;
		}
		elseif ($node instanceof Node\Expr\Cast)
		{
			if ($node instanceof Node\Expr\Cast\Int_)
				return (int)$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\Cast\Array_)
				return (array)$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\Cast\Double)
				return (double)$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\Cast\Bool_)
				return (bool)$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\Cast\String_)
				return (string)$this->evaluate_expression($node->expr);
			// elseif ($node instanceof Node\Expr\Cast\Object_)
			// 	return (object)$this->evaluate_expression($node->expr);
			else
				$this->error("Unknown cast: ",$node);
		}
		elseif ($node instanceof Node\Expr\BooleanNot)
			return !$this->evaluate_expression($node->expr);

		elseif ($node instanceof Node\Expr\BitwiseNot)
			return ~$this->evaluate_expression($node->expr);
		
		elseif ($node instanceof Node\Expr\UnaryMinus)
			return -$this->evaluate_expression($node->expr);
		elseif ($node instanceof Node\Expr\UnaryPlus)
			return +$this->evaluate_expression($node->expr);

		elseif ($node instanceof Node\Expr\PreInc)
		{
			return $this->variable_set($node->var,$this->variable_get($node->var)+1);	
		}
		elseif ($node instanceof Node\Expr\PostInc)
		{
			$t=$this->variable_get($node->var);
			$this->variable_set($node->var,$t+1);
			return $t;
		}
		elseif ($node instanceof Node\Expr\PreDec)
		{
			return $this->variable_set($node->var,$this->variable_get($node->var)-1);	
		}
		elseif ($node instanceof Node\Expr\PostDec)
		{
			$t=$this->variable_get($node->var);
			$this->variable_set($node->var,$t-1);
			return $t;
		}
		elseif ($node instanceof Node\Expr\AssignOp)
		{
			$var=&$this->variable_reference($node->var); //TODO: use variable_set and get here instead
			$val=$this->evaluate_expression($node->expr);
			if ($node instanceof Node\Expr\AssignOp\Plus)
				return $var+=$val;
			elseif ($node instanceof Node\Expr\AssignOp\Minus)
				return $var-=$val;
			elseif ($node instanceof Node\Expr\AssignOp\Mod)
				return $var%=$val;
			elseif ($node instanceof Node\Expr\AssignOp\Mul)
				return $var*=$val;
			elseif ($node instanceof Node\Expr\AssignOp\Div)
				return $var/=$val;
			// elseif ($node instanceof Node\Expr\AssignOp\Pow)
			// 	return $this->variables[$this->name($node->var)]**=$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\AssignOp\ShiftLeft)
				return $var<<=$val;
			elseif ($node instanceof Node\Expr\AssignOp\ShiftRight)
				return $var>>=$val;
			elseif ($node instanceof Node\Expr\AssignOp\Concat)
				return $var.=$val;
			elseif ($node instanceof Node\Expr\AssignOp\BitwiseAnd)
				return $var&=$val;
			elseif ($node instanceof Node\Expr\AssignOp\BitwiseOr)
				return $var|=$val;
			elseif ($node instanceof Node\Expr\AssignOp\BitwiseXor)
				return $var^=$val;
		}
		elseif ($node instanceof Node\Expr\BinaryOp)
		{
			
			$l=$this->evaluate_expression($node->left);
			// $r=$this->evaluate_expression($node->right);
			if ($node instanceof Node\Expr\BinaryOp\Plus)
				return $l+$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Div)
				return $l/$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Minus)
				return $l-$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Mul)
				return $l*$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Mod)
				return $l%$this->evaluate_expression($node->right);
			// elseif ($node instanceof Node\Expr\BinaryOp\Pow)
			// 	return $this->evaluate_expression($node->left)**$this->evaluate_expression($node->right);
			
			elseif ($node instanceof Node\Expr\BinaryOp\Identical)
				return $l===$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\NotIdentical)
				return $l!==$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Equal)
				return $l==$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\NotEqual)
				return $l!=$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Smaller)
				return $l<$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\SmallerOrEqual)
				return $l<=$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Greater)
				return $l>$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\GreaterOrEqual)
				return $l>=$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\LogicalAnd)
				return $l and $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\LogicalOr)
				return $l or $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\LogicalXor)
				return $l xor $this->evaluate_expression($node->right);

			elseif ($node instanceof Node\Expr\BinaryOp\BooleanOr)
				return $l || $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\BooleanAnd)
				return $l && $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\BitwiseAnd)
				return $l & $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\BitwiseOr)
				return $l | $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\BitwiseXor)
				return $l ^ $this->evaluate_expression($node->right);

			elseif ($node instanceof Node\Expr\BinaryOp\ShiftLeft)
				return $l << $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\ShiftRight)
				return $l >> $this->evaluate_expression($node->right);

			elseif ($node instanceof Node\Expr\BinaryOp\Concat)
				return $l . $this->evaluate_expression($node->right);
			// elseif ($node instanceof Node\Expr\BinaryOp\Spaceship)
			// 	return $this->evaluate_expression($node->left)<=>$this->evaluate_expression($node->right);


			else
				$this->error("Unknown binary op: ",$node);
		}
		elseif ($node instanceof Node\Scalar)
		{
			if ($node instanceof Node\Scalar\String_)
				return $node->value;
			elseif ($node instanceof Node\Scalar\DNumber)
				return $node->value;
			elseif ($node instanceof Node\Scalar\LNumber)
				return $node->value;
			elseif ($node instanceof Node\Scalar\Encapsed)
			{
				$res="";
				foreach ($node->parts as $part)	
					if (is_string($part))
						$res.=$part;
					else
						$res.=$this->evaluate_expression($part);

				return $res;
			}
			elseif ($node instanceof Node\Scalar\MagicConst)
			{
				if ($node instanceof Node\Scalar\MagicConst\File)
					return $this->current_file;
				elseif ($node instanceof Node\Scalar\MagicConst\Dir)
					return dirname($this->current_file);
				elseif ($node instanceof Node\Scalar\MagicConst\Line)
					return $node->getLine();
				elseif ($node instanceof Node\Scalar\MagicConst\Function_)
					return $this->current_function;
				elseif ($node instanceof Node\Scalar\MagicConst\Class_)
					return $this->current_class;
				elseif ($node instanceof Node\Scalar\MagicConst\Method_)
					return $this->current_method;
				elseif ($node instanceof Node\Scalar\MagicConst\Namespace_)
					return $this->current_namespace;
				elseif ($node instanceof Node\Scalar\MagicConst\Trait_)
					return $this->current_trait;
			}
			else
				$this->error("Unknown scalar node: ",$node);
		}
		// elseif ($node instanceof Node\Expr\ArrayItem); //this is handled in Array_ implicitly
		
		elseif ($node instanceof Node\Expr\Variable)
		{
			return $this->variable_get($node); //should not be created on access
		}
		elseif ($node instanceof Node\Expr\ConstFetch)
		{
			$name=$this->name($node->name);
			if (array_key_exists($name, $this->constants))
				return $this->constants[$name];
			elseif (defined($name))
				return constant($name);
			else
				$this->error("Undefined constant {$name}");

		}
		elseif ($node instanceof Node\Expr\ErrorSuppress)
		{
			// $error_reporting=error_reporting();
			// error_reporting(0);
			$this->error_silence();
			$res=$this->evaluate_expression($node->expr);
			$this->error_restore(); 
			return $res;
		} 
		elseif ($node instanceof Node\Expr\Exit_)
		{
			$this->terminated=true;	
			if (isset($node->expr))
				return $this->evaluate_expression($node->expr);
			else
				return NULL;
		}
		elseif ($node instanceof Node\Expr\Empty_)
		{
			//return true if not isset, or if false
			$this->error_silence();
			$res=(!$this->variable_isset($node->expr) or ($this->evaluate_expression($node->expr)==false));
			$this->error_restore();
			return $res;
		}
		elseif ($node instanceof Node\Expr\Isset_)
		{
			#FIXME: if the name expression is multipart, and one part of it also doesn't exist this warns. Does PHP too?
			//return false if not isset, or if null
			$res=true;
			foreach ($node->vars as $var)
			{
				if (!$this->variable_isset($var) or $this->evaluate_expression($var)===null)
				{
					$res=false;
					break;
				}
			}
			return $res;
		}
		elseif ($node instanceof Node\Expr\Eval_)
		{
			
			$this->eval_depth++;
			$this->verbose("Now running Eval code...".PHP_EOL);
			
			$code=$this->evaluate_expression($node->expr);
			$ast=$this->parser->parse('<?php '.$code);

			$res=$this->run_code($ast);
			$this->eval_depth--;
			return $res;
		}
		elseif ($node instanceof Node\Expr\ShellExec)
		{
				$res="";
				foreach ($node->parts as $part)	
					if (is_string($part))
						$res.=$part;
					else
						$res.=$this->evaluate_expression($part);

				return shell_exec($res);
		}
		elseif ($node instanceof Node\Expr\Instanceof_)
		{
			$var=$this->evaluate_expression($node->expr);
			$classname=$this->name($node->class);
			return $var instanceof $classname;
		}
		elseif ($node instanceof Node\Expr\Print_)
		{
			$out=$this->evaluate_expression($node->expr);
			$this->output($out);	
			return $out;
		}
		elseif ($node instanceof Node\Expr\Include_)
		{
			$type=$node->type; //1:include,2:include_once,3:require,4:require_once
			$file=$this->evaluate_expression($node->expr);
			
			$realfile =realpath(dirname($this->current_file)."/".$file); //first check the directory of the file using include (as per php)
			if (!file_exists($realfile) or !is_file($realfile)) //second check current dir
				$realfile=realpath($file);
			if ($type%2==0) //once
				if (isset($this->included_files[$realfile])) return true;
			if (!file_exists($realfile) or !is_file($realfile))
				if ($type<=2) //include
				{
					$this->warning("include({$file}): failed to open stream: No such file or directory");
					return false;
				}
				else
				{
					$this->error("require({$file}): failed to open stream: No such file or directory");
					return false;
				}
			$this->run_file($realfile);
		}
		elseif ($node instanceof Node\Expr\Ternary)
		{
			if ($this->evaluate_expression($node->cond)) return $this->evaluate_expression($node->if);
			else return $this->evaluate_expression($node->else);
		}
		// elseif ($node instanceof Node\Expr\New_)
		// {
		// 	$classname=$this->name($node->class);
		// 	if (class_exists($classname))
		// 	{
		// 		$argValues=[];
		// 		foreach ($node->args as $arg)
		// 			$argValues[]=$this->evaluate_expression($arg->value);
				
		// 		ob_start();	
		// 		$r = new ReflectionClass($classname);
		// 		$ret = $r->newInstanceArgs($argValues); #TODO: byref?
		// 		// $ret=new $classname($argValues); //core class
		// 		$output=ob_get_clean();
		// 		$this->output($output);
		// 		return $ret;
		// 	}
		// 	else
		// 	{
		// 		$this->error("Class '{$classname}' not built-in in PHP.",$node);
		// 	}
		// }
		else
			$this->error("Unknown expression node: ",$node);
		return null;
	}

	protected $error_suppression=0;
	function error_silence()
	{
		$this->error_suppression++;
	}
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
	 * Runs a single statement
	 * If input is a statement, it will be run. If its an expression, it will be runned like an statement.
	 * @param  Node $node 
	 */
	protected function run_statement($node)
	{
			if ($node instanceof Node\Stmt\Echo_)
				foreach ($node->exprs as $expr)
					$this->output($this->evaluate_expression($expr));
				// $this->output_array($this->evaluate_expression_array($node->exprs));
			elseif ($node instanceof Node\Stmt\Const_)
				return;
			elseif ($node instanceof Node\Stmt\Function_)
				return;
			elseif ($node instanceof Node\Stmt\If_)
			{
				$done=false;
				if ($this->evaluate_expression($node->cond))
				{
					$done=true;
					$this->run_code($node->stmts);
				}
				else
				{
					if (is_array($node->elseifs))
						foreach ($node->elseifs as $elseif)
						{
							if ($this->evaluate_expression($elseif->cond))
							{
								$done=true;
								$this->run_code($elseif->stmts);
								break;
							}
						}
					if (!$done and isset($node->else))
						$this->run_code($node->else->stmts);
				}
			}
			elseif ($node instanceof Node\Stmt\Return_)
			{
				// print_r($node);
				if ($node->expr)
					$this->return_value=$this->evaluate_expression($node->expr);
				else
					$this->return_value=null;
				$this->return=true;
				return $this->return_value;
			}
			elseif ($node instanceof Node\Stmt\For_) //Loop 1
			{
				$i=0;
				$this->loop_depth++;
				for ($this->run_code($node->init);$this->evaluate_expression($node->cond[0]);$this->run_code($node->loop))
				{
					$i++;	
					$this->run_code($node->stmts);
					if ($this->loop_condition($i))
						break;
				}
				$this->loop_depth--;
			}
			elseif ($node instanceof Node\Stmt\While_) //Loop 2
			{
				$i=0;
				$this->loop_depth++;
				while ($this->evaluate_expression($node->cond))
				{
					$i++;
					$this->run_code($node->stmts);
					if ($this->loop_condition($i))
						break;
				}
				$this->loop_depth--;
			}
			elseif ($node instanceof Node\Stmt\Do_) //Loop 3
			{
				$i=0;
				$this->loop_depth++;
				do
				{
					$i++;
					$this->run_code($node->stmts);
					if ($this->loop_condition($i))
						break;
				}
				while ($this->evaluate_expression($node->cond));
				$this->loop_depth--;
			}
			elseif ($node instanceof Node\Stmt\Foreach_) //Loop 4
			{
				$list=$this->evaluate_expression($node->expr);
				$keyed=false;
				if (isset($node->keyVar))
				{
					$keyed=true;	
					if (!$this->variable_isset($node->keyVar))
						$this->variable_set($node->keyVar);
					$keyVar=&$this->variable_reference($node->keyVar);
				}
				if (!$this->variable_isset($node->valueVar))
					$this->variable_set($node->valueVar);
				$valueVar=&$this->variable_reference($node->valueVar);

				$this->loop_depth++;
				foreach ($list as $k=>$v)
				{
					if ($keyed)
						$keyVar=$k;
					$valueVar=$v;
					$this->run_code($node->stmts);
					
					if ($this->loop_condition())
						break;
				}
				$this->loop_depth--;
			}
			elseif ($node instanceof Node\Stmt\Declare_)
			{
				//TODO: handle tick function here, by wrapping it
				$data=[];
				$code="declare(";
				foreach ($node->declares as $declare)
				{
					$data[$declare->key]=$this->evaluate_expression($declare->value);
					$code.="{$declare->key}='".$this->evaluate_expression($declare->value)."',";
				}
				$code=substr($code,0,-1).");"; 
				eval($code);
			}
			elseif ($node instanceof Node\Stmt\Switch_)
			{
				$arg=$this->evaluate_expression($node->cond);
				foreach ($node->cases as $case)
				{
					if ($case->cond===NULL /* default case*/ or $this->evaluate_expression($case->cond)==$arg)
						$this->run_code($case->stmts);
					if ($this->loop_condition())
						break;
				}
			} 
			elseif ($node instanceof Node\Stmt\Break_)
			{
				if (isset($node->num))
					$this->break+=$this->evaluate_expression($node->num);
				else
					$this->break++;
			}
			elseif ($node instanceof Node\Stmt\Continue_)
			{
				//basically, continue 3 means break 2 inner loops and continue on the outer loop
				if (isset($node->num))
					$num=$this->evaluate_expression($node->num);
				else
					$num=1;
				$this->continue+=$num;
			}
			elseif ($node instanceof Node\Stmt\Unset_)
			{
				foreach ($node->vars as $var)
					$this->variable_unset($var);
			}
			elseif ($node instanceof Node\Stmt\Throw_)
			{
				if ($this->try>0)
					throw $this->evaluate_expression($node->expr);
				else
					$this->error("Throw that is not catched");

			}
			elseif ($node instanceof Node\Stmt\TryCatch)
			{
				$this->try++;
				try {
					$this->run_code($node->stmts);
				}
				catch (Exception $e)
				{
					$this->try--; //no longer in the try
					foreach ($node->catches as $catch)
					{
						//each has type, the exception type, var, the exception variable, and stmts
						$type=$this->name($catch->type);
						if ($e instanceof $type)
						{
							$this->variable_set($catch->var,$e);
							$this->run_code($catch->stmts);
							break;
						}
					}
					$this->try++; //balance off with the one below
				}
				$this->try--;
			}
			elseif ($node instanceof Node\Expr\Exit_)
			{
				$res=$this->evaluate_expression($node->expr);
				if (!is_numeric($res))	
					$this->output($res);
				$this->terminated=true;
				return $res;
			}
			elseif ($node instanceof Node\Stmt\Static_)
			{
				if (end($this->trace)->type=="function" and  isset($this->functions[strtolower(end($this->trace)->name)])) //statc inside a function
				{
					$statics=&$this->functions[strtolower($this->current_function)]->statics;
					foreach ($node->vars as $var)
					{
						$name=$this->name($var->name);
						if (!array_key_exists($name,$statics))
							$statics[$name]=$this->evaluate_expression($var->default);
						$this->variables[$name]=&$statics[$name];
					}
				}
				else
				{
					#TODO:
					$this->error("Global statics not yet supported");

				}
			}
			elseif ($node instanceof Node\Stmt\InlineHTML)
				$this->output($node->value); 
			elseif ($node instanceof Node\Stmt\Global_)
			{
				foreach ($node->vars as $var)
				{
					$name=$this->name($var->name);
					$globals_=&$this->variable_reference("GLOBALS");

					if (!isset($globals_[$name]))
						$globals_[$name]=null; //create
					$this->variables[$name]=&$globals_[$name];
				}
			}
			elseif ($node instanceof Node\Expr)
				$this->evaluate_expression($node);
			else
			{
				$this->error("Unknown node type: ",$node);	
			}
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


	private $parsed=[];
	private $parsed_modified=false;
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
	function __construct()
	{
		$this->variable_stack['global']=array();
		$this->variables=&$this->variable_stack['global'];
		$this->parser = new PhpParser\Parser(new PhpParser\Lexer);
    	$this->init();
	}
	function __destruct()
	{
		$this->verbose(sprintf("Memory usage: %.2fMB (%.2fMB)\n",memory_get_usage()/1024.0/1024.0,memory_get_peak_usage()/1024.0/1024.0));
	}




}
//this loads all functions, so that auto-mock will replace them
foreach (glob(__DIR__."/mocks/*.php") as $mock)
	require_once $mock;




if (isset($argc) and $argv[0]==__FILE__)
{
	die("Should not run this directly.".PHP_EOL);
}
