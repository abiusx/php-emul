<?php
require_once __DIR__."/PHP-Parser/lib/bootstrap.php";
use PhpParser\Node;
#remaining for procedural completeness: closure,closureUse
#also callbacks, any function in PHP that accepts callbacks will fail because real callbacks do not exist. they all should be mocked
#e.g set_error_handler, register_shutdown_function, preg_replace_callback
#TODO: PhpParser\Node\Stmt\StaticVar vs PhpParser\Node\Stmt\Static_
class Emulator
{	
	public $infinite_loop	=	1000; #1000000;
	public $direct_output	=	true;
	public $verbose			=	false;
	public $auto_mock		=	true;

	protected $current_node,$current_file,$current_line;
	protected $current_function;
	public $included_files=[];
	public $output;
	public $variables=[]; #TODO: make this a object, so that it can lookup magic variables, and retain them on push/pop
	public $functions=[];
	public $constants=[];
	public $parser;
	public $variable_stack=[];
	public $terminated=false;

	public $mock_functions=[];

	public $trace=[];
	function __construct()
	{
		$this->parser = new PhpParser\Parser(new PhpParser\Lexer);
    	$this->init();
	}
	function init()
	{
		foreach ($GLOBALS as $k=>$v)
		{
			if ($k=="GLOBALS") continue;
			$this->variables[$k]=$v;
		}
		// $this->variables['_POST']=isset($_POST)?$_POST:array();
		if ($this->auto_mock)
		foreach(get_defined_functions()['internal'] as $function) //get_defined_functions gives internal and user subarrays.
		{
			if (function_exists($function."_mock"))
				$this->mock_functions[$function]=$function."_mock";
		}
	}


	protected function &globals()
	{
		#FIXME: this should return byref! otherwise changes don't propagate
		if (count($this->variable_stack))
			return $this->variable_stack[0];
		else
			return $this->variables;
	}
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
		echo "PHP-Emul {$str}:  {$errstr} in {$file} on line {$line} ($file2:$line2)",PHP_EOL;
		if ($this->verbose)
			debug_print_backtrace();
		if ($fatal) 
			$this->terminated=true;
		return true;
	}
	protected function error($msg,$node=null)
	{
		echo "Emulation Error: ";
		$this->_error($msg,$node);
		$this->terminated=true;
	}
	private function _error($msg,$node=null,$details=true)
	{

		echo $msg," in ",$this->current_file," on line ",$this->current_line,PHP_EOL;
		if ($details)
		{
			print_r($node);
			if ($this->verbose)
				debug_print_backtrace();
		}
	}
	protected function notice($msg,$node=null)
	{
		echo "Emulation Notice: ";
		$this->_error($msg,$node,false);
	}

	protected function warning($msg,$node=null)
	{
		echo "Emulation Warning: ";
		$this->_error($msg,$node);
		// trigger_error($msg);
	}
	function output()
	{
		$args=func_get_args();
		$data=implode("",$args);
		$this->output.=$data;
		if ($this->direct_output)
			echo $data;
	}
	protected function push()
	{
		array_push($this->variable_stack, $this->variables);
		$this->variables=[];
	}
	protected function pop()
	{
		$this->variables=array_pop($this->variable_stack);
	}
	/**
	 * Runs a subcode, used by run_function and run_method
	 * @param  [type] $function the parsed declaration of function
	 * @param  [type] $args          [description]
	 * @return [type]                [description]
	 */
	protected function run_sub($function,$args)
	{
		reset($args);
		$count=count($args);
		$index=0;
		$function_variables=[];
		foreach ($function->params as $param)
		{
			if ($index>=$count) //all args consumed, either defaults or error
			{
				if (isset($param->default))
					$function_variables[$param->name]=$this->evaluate_expression($param->default);
				else
				{
					$this->warning("Missing argument ".($index)." for {$name}()");
					return null;
				}

			}
			else //args still exist, copy to current symbol table
			{
				if ($param->byRef)	// byref handle
				{
					$function_variables[$this->name($param)]=&$this->reference(current($args)->value);
				}
				else //byval
				{
					$function_variables[$this->name($param)]=$this->evaluate_expression(current($args)->value);
				}
				next($args);
			}
			$index++;
		}
		$this->push();
		$this->variables=$function_variables;
		$res=$this->run_code($function->code);
		$this->pop();
		return $res;
	}
	/**
	 * Runs a function from user-defined functions
	 * @param  [type] $name [description]
	 * @param  [type] $args [description]
	 * @return [type]       [description]
	 */
	protected function run_function($name,$args)
	{
		if ($this->verbose)
			echo "\tRunning {$name}()...",PHP_EOL;
		#FIXME: function does not exist?
		$last_file=$this->current_file;
		$last_function=$this->current_function;
		$this->current_function=$name;
		$this->current_file=$this->functions[$name]->file;

		array_push($this->trace, (object)array("type"=>"function","name"=>$name));
		$res=$this->run_sub($this->functions[$name],$args);
		array_pop($this->trace);

		$this->current_function=$last_function;
		$this->current_file=$last_file;
		if ($this->return)
			$this->return=false;	
		return $res;
	}
	/**
	 * Evaluate all nodes of type Node\Expr and return appropriate value
	 * @param  Node $ast Abstract Syntax Tree node
	 * @return mixed      value
	 */
	protected function evaluate_expression($ast)
	{
		$node=$ast;
		$this->current_node=$node;
		if (false);
		elseif (is_array($node))
			die("array node!");
		elseif ($node instanceof Node\Expr\FuncCall)
		{
			$name=$this->name($node);
			// $name=$this->evaluate_expression($node->name);
			if (isset($this->functions[$name]))
				return $this->run_function($name,$node->args); //user function
			elseif (function_exists($name))
			{
				$argValues=[];
				foreach ($node->args as $arg)
				{
					if ($arg->value instanceof Node\Expr\Variable) //byref probably?
						$argValues[]=&$this->reference(($arg->value));
					else
						$argValues[]=$this->evaluate_expression($arg->value);
				}
				#FIXME: handle critical internal functions (e.g function_exists, ob_start, etc.)
				if (array_key_exists($name, $this->mock_functions)) //mocked
				{
					if (!function_exists($this->mock_functions[$name]))
						$this->error("Mocked function '{$this->mock_functions[$name]}' does not exists to mock '{$name}'");
					array_unshift($argValues, $this); //emulator is first argument in mock functions
					$ret=call_user_func_array($this->mock_functions[$name],$argValues); //core function
				}
				else //original core function
				{
					ob_start();	
					$ret=call_user_func_array($name,$argValues); //core function
					$output=ob_get_clean();
					$this->output($output);
				}
				return $ret;
			}
			else
			{
				$this->error("Call to undefined function {$name}()",$node);
			}
		}
		elseif ($node instanceof Node\Expr\AssignRef)
		{
			// $originalVar=$this->name($node->expr);
			$originalVar=&$this->reference($node->expr);
			$var=&$this->reference($node->var);
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
				foreach ($node->var->vars as $var)
				{
					if ($var instanceof Node\Expr\Variable)
					{
						$var=&$this->reference($var);
						$var=current($resArray);
						next($resArray);
					}
				}
			}
			else
			{
				$var=&$this->reference($node->var);
				return $var=$this->evaluate_expression($node->expr);
			}
				// $this->error("Unknown assign: ",$node);
		}
		elseif ($node instanceof Node\Expr\ArrayDimFetch) //access multidimensional arrays $x[...][..][...]
			return $this->reference($node);
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
			elseif ($node instanceof Node\Expr\Cast\Object_)
				return (object)$this->evaluate_expression($node->expr);
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
			$var=&$this->reference($node->var);	
			return ++$var;
		}
		elseif ($node instanceof Node\Expr\PostInc)
		{
			$var=&$this->reference($node->var);	
			return $var++;
		}
		elseif ($node instanceof Node\Expr\PreDec)
		{
			$var=&$this->reference($node->var);	
			return --$var;
		}
		elseif ($node instanceof Node\Expr\PostDec)
		{
			$var=&$this->reference($node->var);	
			return $var--;
		}
		elseif ($node instanceof Node\Expr\AssignOp)
		{
			$var=&$this->reference($node->var);
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
			#FIXME: this is not lazy evaluation!
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
			return $this->reference($node);
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
			#TODO: error handling
			return $this->evaluate_expression($node->expr);
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
			#FIXME: two modes, one variable, one expression=null
			$expr=$this->evaluate_expression($node->expr);
			return empty($expr);
		}
		elseif ($node instanceof Node\Expr\Isset_)
		{
			#FIXME: if the name expression is multipart, and one part of it also doesn't exist this warns. Does PHP too?
			foreach ($node->vars as $var)
			{
				$temp=&$this->reference($var,false);
				if (!isset($temp))
					return false;
			}
			return true;
		}
		elseif ($node instanceof Node\Expr\Eval_)
		{
			#FIXME: do not use eval!
			ob_start();
			$res=eval($this->evaluate_expression($node->expr));
			$out=ob_get_clean();
			$this->output($out);
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
			#TODO: before all check include paths
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
		elseif ($node instanceof Node\Expr\New_)
		{
			$classname=$this->name($node->class);
			if (isset($this->classes[$classname]))
			{
				return $this->new_object($classname,$node->args); //user function
			}
			else
			{
				$argValues=[];
				foreach ($node->args as $arg)
					$argValues[]=$this->evaluate_expression($arg->value);
				#FIXME: handle critical internal classes (if any)
				ob_start();	
				$r = new ReflectionClass($classname);
				$ret = $r->newInstanceArgs($argValues); #TODO: byref?
				// $ret=new $classname($argValues); //core class
				$output=ob_get_clean();
				$this->output($output);
				return $ret;
			}
		}
		else
			$this->error("Unknown expression node: ",$node);
		return null;
	}
	protected function new_object($name,array $args)
	{
		echo "Not implemented.";
		print_r($args);
	}
	/**
	 * Returns a reference to a variable, so that it can be modified.
	 * 
	 * It should be used like this: $var=&$this->reference(...);
	 * @param  Node 	$node [description]
	 * @param  bool 	$create whether to create the variable if it does not exist, or not.
	 * @return reference       reference to variable
	 */
	protected function &reference($node,$create=true)
	{
		if ($node===null)
		{
			$this->notice("Undefined variable: {$node}");	
			return null;
		}
		elseif (is_string($node))
		{
			if (array_key_exists($node, $this->variables))
			{
				// echo $node,"=",$this->variables[$node],PHP_EOL;
				return $this->variables[$node];
			}
			elseif ($create)
			{
				$this->variables[$node]=null;
				return $this->variables[$node];
			}
			else
			{
				$this->notice("Undefined variable: {$node}");	
				return null; //variable not exists
			}
		}
		elseif ($node instanceof Node\Expr\ArrayDimFetch)
		{
			$t=$node;
			$dim=0;
			$indexes=[];
			//each ArrayDimFetch has a var and a dim. var can either be a variable, or another ArrayDimFetch
			while ($t instanceof Node\Expr\ArrayDimFetch)
			{
				$dim++;
				if ($t->dim)
					$indexes[]=$this->evaluate_expression($t->dim);
				else
					$indexes[]=NULL;
				$t=$t->var;
			}
			$indexes=array_reverse($indexes);

			// $varName=$this->name($t);
			// $base=&$this->variables[$varName];
			$base=&$this->reference($t);
			foreach ($indexes as $index)
			{
				if ($index===NULL)
				{
					//it might be $a[]=
					$base[]=NULL;
					end($base);
					$index=key($base);
				}
				$base=&$base[$index];
			}
			return $base;
		}
		elseif ($node instanceof Node\Expr\Variable)
		{
			// if (is_string($node->name))
				return $this->reference($node->name);
			// else
			// {

			// 	return $this->reference($this->evaluate_expression($node->name));
			// }
		}
		else
			$this->error("Can not find variable reference of this node type.",$node);
	}
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
	public function run_file($file)
	{
		$last_file=$this->current_file;
		$this->current_file=realpath($file);

		echo "Now running {$this->current_file}...",PHP_EOL;
		
		$this->included_files[$this->current_file]=true;
		
		$code=file_get_contents($file);
		$ast=$this->parser->parse($code);

		$res=$this->run_code($ast);
		if ($this->return)
			$this->return=false;
		$this->current_file=$last_file;
		return $res;
	}
	
	function start($file,$chdir=true)
	{
		chdir(dirname($file));
		$file=basename($file);
		ini_set("memory_limit",-1);
		set_error_handler(array($this,"error_handler"));
		$res=$this->run_file($file);
		restore_error_handler();

		return $res;
	}
	protected $break=0,$continue=0;
	protected $try=0,$loop=0;

	protected $return=false;
	protected $return_value=null;

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
			elseif ($node instanceof Node\Stmt\For_)
			{
				$i=0;
				$this->loop++;
				for ($this->run_code($node->init);$this->evaluate_expression($node->cond[0]);$this->run_code($node->loop))
				{
					$i++;	
					$this->run_code($node->stmts);
					if ($this->break)
					{
						$this->break--;
						if ($this->break) //nested break, the 2 here ensures that the rest of statements in current loop don't execute
							return;
						else
							return;
					}
					if ($this->continue)
					{
						$this->continue--;
						if ($this->continue)
							return; 
					}
					if ($i>$this->infinite_loop)
					{
						$this->error("Infinite loop");
						return; 
					}
				}
				$this->loop--;
			}
			elseif ($node instanceof Node\Stmt\While_)
			{
				$i=0;
				while ($this->evaluate_expression($node->cond))
				{
					$i++;
					$this->run_code($node->stmts);
					if ($this->break)
					{
						$this->break--;
						if ($this->break) //nested break, the 2 here ensures that the rest of statements in current loop don't execute
							return;
						else
							return;
					}
					if ($this->continue)
					{
						$this->continue--;
						if ($this->continue)
							return; 
					}
					if ($i>$this->infinite_loop)
					{
						$this->error("Infinite loop");
						return; 
					}
				}
			}
			elseif ($node instanceof Node\Stmt\Do_)
			{
				$i=0;
				do
				{
					$this->run_code($node->stmts);
					$i++;
					if ($this->break)
					{
						$this->break--;
						if ($this->break) //nested break, the 2 here ensures that the rest of statements in current loop don't execute
							return;
						else
							return;
					}
					if ($this->continue)
					{
						$this->continue--;
						if ($this->continue)
							return; 
					}
					if ($i>$this->infinite_loop)
					{
						$this->error("Infinite loop");
						return; 
					}
				}
				while ($this->evaluate_expression($node->cond));
			}
			elseif ($node instanceof Node\Stmt\Foreach_)
			{
				$list=$this->evaluate_expression($node->expr);
				$keyed=false;
				if (isset($node->keyVar))
				{
					$keyed=true;	
					$keyVar=&$this->reference($node->keyVar);
				}
				$valueVar=&$this->reference($node->valueVar);
				foreach ($list as $k=>$v)
				{
					if ($keyed)
						$keyVar=$k;
					$valueVar=$v;
					$this->run_code($node->stmts);
					if ($this->break)
					{
						$this->break--;
						if ($this->break) //nested break, the 2 here ensures that the rest of statements in current loop don't execute
							return;
						else
							return;
					}
					if ($this->continue)
					{
						$this->continue--;
						if ($this->continue)
							return; 
					}

				}
			}
			elseif ($node instanceof Node\Stmt\Declare_)
			{
				$data=[];
				$code="declare(";
				foreach ($node->declares as $declare)
				{
					$data[$declare->key]=$this->evaluate_expression($declare->value);
					$code.="{$declare->key}='".$this->evaluate_expression($declare->value)."',";
				}
				$code=substr($code,0,-1).");"; #FIXME: everything is strings atm
				eval($code);
			}
			elseif ($node instanceof Node\Stmt\Switch_)
			{
				$arg=$this->evaluate_expression($node->cond);
				foreach ($node->cases as $case)
				{
					if ($case->cond===NULL /* default case*/ or $this->evaluate_expression($case->cond)==$arg)
						$this->run_code($case->stmts);
					if ($this->break)
					{
						$this->break--;
						if ($this->break) //nested break, the 2 here ensures that the rest of statements in current loop don't execute
							return;
						else
							return;
					}
					if ($this->continue)
					{
						$this->continue--;
						if ($this->continue)
							return; 
					}
				}
			} 
			elseif ($node instanceof Node\Stmt\Break_)
			{
				if (isset($node->num))
					$this->break+=$this->evaluate_expression($node->num);
				else
					$this->break++;
				return; //break this loop of run_code, and have the real loop break because $this->break > 0
			}
			elseif ($node instanceof Node\Stmt\Continue_)
			{
				//basically, continue 3 means break 2 inner loops and continue on the outer loop
				if (isset($node->num))
					$num=$this->evaluate_expression($node->num);
				else
					$num=1;
				// $this->continue++;
				// $this->break+=$num-1;
				$this->continue+=$num;

				return ;
			}
			elseif ($node instanceof Node\Stmt\Unset_)
			{
				foreach ($node->vars as $var)
				{
					// print_r($var);
					$temp=&$this->reference($var,false);
					unset($temp); #TODO: make sure this works alright
				}
			}
			elseif ($node instanceof Node\Stmt\Throw_)
			{
				if ($this->try>0)
					throw $this->evaluate_expression($node->expr);
				//TODO: do something on else, uncatched throw, fatal error

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
						$var=&$this->reference($catch->var);
						if ($e instanceof $type)
						{
							$var=$e;
							$this->run_code($catch->stmts);
							break;
						}
					}
					$this->try++; //balance off with the one below
				}
				$this->try--;
			}
			elseif ($node instanceof Node\Expr\Exit_)
				return $this->evaluate_expression($node);
			elseif ($node instanceof Node\Stmt\Static_)
			{
				if (end($this->trace)->type=="function" and  isset($this->functions[end($this->trace)->name])) //statc inside a function
				{
					$statics=&$this->functions[$this->current_function]->statics;
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
					$this->error("Global statics not yet supported");

				}
			}
			elseif ($node instanceof Node\Stmt\InlineHTML)
				$this->output($node->value); //FIXME: u sure this is the only way inline is? just strings?
			elseif ($node instanceof Node\Stmt\Global_)
			{
				foreach ($node->vars as $var)
				{
					$name=$this->name($var->name);
					if (array_key_exists($name,$this->globals()))
						$this->variables[$name]=$this->globals()[$name];
					else
						$this->notice("Undefined index '{$name}' in globals",$this->globals());
				}
			}
			elseif ($node instanceof Node\Expr)
				$this->evaluate_expression($node);
			else
			{
				$this->error("Unknown node type: ",$node);	
			}
	}
	protected function get_declarations($node)
	{
		if (0);
		elseif ($node instanceof Node\Stmt\Function_)
		{
			// echo PHP_EOL;
			$name=$this->name($node->name);
			$this->functions[$name]=(object)array("params"=>$node->params,"code"=>$node->stmts,"file"=>$this->current_file,"statics"=>[]); #FIXME: name
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
	protected function run_code($ast)
	{
		//first pass, get all definitions
		foreach ($ast as $node)
			$this->get_declarations($node);

		//second pass, execute
		foreach ($ast as $node)
		{
			$this->current_node=$node;
			if ($node->getLine()!=$this->current_line)
			{
				$this->current_line=$node->getLine();
				if ($this->verbose) 
					echo "\t\tLine {$this->current_line}",PHP_EOL;
			}
			$this->run_statement($node);
			if ($this->terminated) return null;
			if ($this->return) return $this->return_value;
			if ($this->break) break;
			if ($this->continue) break;
		}
	}	
	function __destruct()
	{
	}
}


function get_defined_vars_mock(Emulator $emul)
{
	echo "mocked get_defined_vars called!",PHP_EOL;
	return $emul->variables;
}



















// $_GET['url']='http://abiusx.com/blog/wp-content/themes/nano2/images/banner.jpg';
if (isset($argc) and $argv[0]==__FILE__)
{
	$x=new Emulator;
	$x->start("sample-stmts.php");
	// echo(($x->output));
}
// $x->start("yapig-0.95b/index.php");
// echo "Output of size ".strlen($x->output)." was generated.",PHP_EOL;
// var_dump(substr($x->output,-100));
// echo PHP_EOL,"### Variables ###",PHP_EOL;
// var_dump($x->variables);