<?php
require_once __DIR__."/PHP-Parser/lib/bootstrap.php";
use PhpParser\Node;
#remaining for procedural completeness: closure,closureUse
#TODO: error handling is very vague, and makes fixing bugs very hard.
#FIXME: all $this->name instances should be fixed, create a sample file and include everything there
class Emulator
{	
	public $infinite_loop	=	1000; #1000000;
	public $direct_output	=	false;
	public $verbose			=	false;

	protected $current_node,$current_file,$current_line;
	protected $current_function;
	protected $current_class,$current_method,$current_trait;
	protected $current_namespace;
	public $included_files=[];
	public $output;
	public $variables=[]; #TODO: make this a object, so that it can lookup magic variables, and retain them on push/pop
	public $functions=[];
	public $classes=[];
	public $constants=[];
	public $parser;
	public $variable_stack=[];
	public $terminated=false;


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
	private function _error($msg,$node=null)
	{

		echo $msg," in ",$this->current_file," on line ",$this->current_line,PHP_EOL;
		print_r($node);
		if ($this->verbose)
			debug_print_backtrace();
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
	protected function run_function($name,$args)
	{
		if ($this->verbose)
			echo "\tRunning {$name}()...",PHP_EOL;
		#FIXME: should push after every parameter expression is evaluated, as they may have side effects on symbol table
		#currently we push a copy, and modifications are not preserved. We do it this way for now because references are hard to handle.
		$last_file=$this->current_file;
		$last_function=$this->current_function;
		$this->current_function=$name;
		$variables=$this->variables;
		$this->push();
		$this->variables=$variables;

		end($this->variable_stack);
		$current_symbol_table=&$this->variable_stack[key($this->variable_stack)];
		$function=$this->functions[$name];
		// if (count($function['params'])!=count($args))
			// $this->error("{$name} expects ".count($function['params'])." arguments but received ".count($args));
		$this->current_file=$this->functions[$name]['file'];
		reset($args);
		$count=count($args);
		$index=0;
		$function_variables=[];
		foreach ($function['params'] as $param)
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
					$function_variables[$this->name($param)]=&$current_symbol_table[$this->name(current($args)->value)];
				else //byval
				{
					$function_variables[$this->name($param)]=$this->evaluate_expression(current($args)->value);
				}
				next($args);
			}
			$index++;
		}
		$this->variables=$function_variables;
		$res=$this->run_code($function['code']);
		$this->pop();
		$this->current_function=$last_function;
		$this->current_file=$last_file;
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
					{
						$argName=$this->name($arg->value);
						$argValues[]=&$this->variables[$argName];
					}	
					else
						$argValues[]=$this->evaluate_expression($arg->value);
				}
				#FIXME: handle critical internal functions (e.g function_exists, ob_start, etc.)
				ob_start();	
				$ret=call_user_func_array($name,$argValues); //core function
				$output=ob_get_clean();
				$this->output($output);
				return $ret;
			}
			else
			{
				$this->error("Call to undefined function {$name}()",$node);
			}
		}
		elseif ($node instanceof Node\Expr\AssignRef)
		{
			$originalVar=$this->name($node->expr);
			$var=$this->name($node->var);
			$this->variables[$var]=&$this->variables[$originalVar];
		}
		elseif ($node instanceof Node\Expr\Assign)
		{
			if ($node->var instanceof Node\Expr\Variable)	
			{
				$name=$this->name($node->var);
				$this->variables[$name]=$this->evaluate_expression($node->expr);	
				return $this->variables[$name];
			}
			elseif ($node->var instanceof Node\Expr\List_) //list(x,y)=f()
			{
				$resArray=$this->evaluate_expression($node->expr);
				if (count($resArray)!=count($node->var->vars))
					$this->warning("list() used but number of arguments do not match");
				reset($resArray);
				foreach ($node->var->vars as $var)
				{
					if ($var instanceof Node\Expr\Variable)
					{
						$this->variables[$this->name($var->name)]=current($resArray);
						next($resArray);
					}
				}
			}
			elseif ($node->var instanceof Node\Expr\ArrayDimFetch) //$x[...][...]=...
			{
				$t=$node->var;
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
				$varName=$this->name($t);

				$base=&$this->variables[$varName];
				foreach ($indexes as $index)
				{
					if (!$index)
					{
						//it might be $a[]=
						$base[]=NULL;
						end($base);
						$index=key($base);
					}
					$base=&$base[$index];
					#TODO: this ArrayDimFetch should be used everywhere. #FIXME the rest should not work fine
				}
				$base=$this->evaluate_expression($node->expr);
				// $this->variables[$this->name($node->var->var->name)][$this->name($node->var->dim)]=$this->evaluate_expression($node->expr);
			}
			else
				$this->error("Unknown assign: ",$node);
		}
		elseif ($node instanceof Node\Expr\ArrayDimFetch) //access multidimensional arrays $x[...][..][...]
		{
			$t=$node;
			$dim=0;
			$indexes=[];
			//each ArrayDimFetch has a var and a dim. var can either be a variable, or another ArrayDimFetch
			while ($t instanceof Node\Expr\ArrayDimFetch)
			{
				$dim++;
				$indexes[]=$this->evaluate_expression($t->dim);
				$t=$t->var;
			}
			$indexes=array_reverse($indexes);
			$varName=$this->name($t);
			if (!isset($this->variables[$varName]))
				$this->warning("Variable '\$$varName' not defined");
			$base=&$this->variables[$varName];
			foreach ($indexes as $index)
			{
				if (!isset($base[$index]))	
					$this->warning("Undefined index {$index} for '\$$varName'");
				$base=&$base[$index];
			}
			return $base;
			// $base=$this->evaluate_expression($node->expr);


		}
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
			return ++$this->variables[$this->name($node->var)];
		elseif ($node instanceof Node\Expr\PostInc)
			return $this->variables[$this->name($node->var)]++;
		elseif ($node instanceof Node\Expr\PreDec)
			return --$this->variables[$this->name($node->var)];
		elseif ($node instanceof Node\Expr\PostDec)
			return $this->variables[$this->name($node->var)]--;

		elseif ($node instanceof Node\Expr\AssignOp)
		{
			if ($node instanceof Node\Expr\AssignOp\Plus)
				return $this->variables[$this->name($node->var)]+=$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\AssignOp\Minus)
				return $this->variables[$this->name($node->var)]=$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\AssignOp\Mod)
				return $this->variables[$this->name($node->var)]*=$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\AssignOp\Mul)
				return $this->variables[$this->name($node->var)]*=$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\AssignOp\Div)
				return $this->variables[$this->name($node->var)]/=$this->evaluate_expression($node->expr);
			// elseif ($node instanceof Node\Expr\AssignOp\Pow)
			// 	return $this->variables[$this->name($node->var)]**=$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\AssignOp\ShiftLeft)
				return $this->variables[$this->name($node->var)]<<=$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\AssignOp\ShiftRight)
				return $this->variables[$this->name($node->var)]>>=$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\AssignOp\Concat)
				return $this->variables[$this->name($node->var)].=$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\AssignOp\BitwiseAnd)
				return $this->variables[$this->name($node->var)]&=$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\AssignOp\BitwiseOr)
				return $this->variables[$this->name($node->var)]|=$this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\AssignOp\BitwiseXor)
				return $this->variables[$this->name($node->var)]^=$this->evaluate_expression($node->expr);
		}
		elseif ($node instanceof Node\Expr\BinaryOp)
		{
			if ($node instanceof Node\Expr\BinaryOp\Plus)
				return $this->evaluate_expression($node->left)+$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Div)
				return $this->evaluate_expression($node->left)/$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Minus)
				return $this->evaluate_expression($node->left)-$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Mul)
				return $this->evaluate_expression($node->left)*$this->evaluate_expression($node->right);
			// elseif ($node instanceof Node\Expr\BinaryOp\Pow)
			// 	return $this->evaluate_expression($node->left)**$this->evaluate_expression($node->right);
			
			elseif ($node instanceof Node\Expr\BinaryOp\Identical)
				return $this->evaluate_expression($node->left)===$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\NotIdentical)
				return $this->evaluate_expression($node->left)!==$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Equal)
				return $this->evaluate_expression($node->left)==$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\NotEqual)
				return $this->evaluate_expression($node->left)!=$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Smaller)
				return $this->evaluate_expression($node->left)<$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\SmallerOrEqual)
				return $this->evaluate_expression($node->left)<=$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Greater)
				return $this->evaluate_expression($node->left)>$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\GreaterOrEqual)
				return $this->evaluate_expression($node->left)>=$this->evaluate_expression($node->right);
			
			elseif ($node instanceof Node\Expr\BinaryOp\LogicalAnd)
				return $this->evaluate_expression($node->left) and $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\LogicalOr)
				return $this->evaluate_expression($node->left) or $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\LogicalXor)
				return $this->evaluate_expression($node->left) xor $this->evaluate_expression($node->right);

			elseif ($node instanceof Node\Expr\BinaryOp\BooleanOr)
				return $this->evaluate_expression($node->left) || $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\BooleanAnd)
				return $this->evaluate_expression($node->left) && $this->evaluate_expression($node->right);

			elseif ($node instanceof Node\Expr\BinaryOp\BitwiseAnd)
				return $this->evaluate_expression($node->left) & $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\BitwiseOr)
				return $this->evaluate_expression($node->left) | $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\BitwiseXor)
				return $this->evaluate_expression($node->left) ^ $this->evaluate_expression($node->right);

			elseif ($node instanceof Node\Expr\BinaryOp\ShiftLeft)
				return $this->evaluate_expression($node->left)<<$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\ShiftRight)
				return $this->evaluate_expression($node->left)>> $this->evaluate_expression($node->right);

			elseif ($node instanceof Node\Expr\BinaryOp\Concat)
				return $this->evaluate_expression($node->left).$this->evaluate_expression($node->right);

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
			$name=$this->name($node);
			if (array_key_exists($name, $this->variables))
				return $this->variables[$name];
			else
				$this->error("Undefined variable {$node->name}",$this->variables);
		}
		elseif ($node instanceof Node\Expr\ConstFetch)
		{
			$name=$this->name($node->name);
			if (isset($this->constants[$name]))
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
			if (isset($node->expr))
				return $this->evaluate_expression($node->expr);
			else
				return NULL;
		elseif ($node instanceof Node\Expr\Empty_)
			return empty($this->variables[$this->name($node->expr)]);
		elseif ($node instanceof Node\Expr\Isset_)
		{
			#FIXME: if the name expression is multipart, and one part of it also doesn't exist this warns. Does PHP too?
			foreach ($node->vars as $var)
			{
				if ($var instanceof Node\Expr\Variable)
				{

					if (!isset($this->variables[$this->name($var)]))
						return false;
				}
				elseif ($var instanceof Node\Expr\ArrayDimFetch)
				{
					$t=$var;
					$dim=0;
					$indexes=[];
					//each ArrayDimFetch has a var and a dim. var can either be a variable, or another ArrayDimFetch
					while ($t instanceof Node\Expr\ArrayDimFetch)
					{
						$dim++;
						$indexes[]=$this->evaluate_expression($t->dim);
						$t=$t->var;
					}
					$indexes=array_reverse($indexes);
					$varName=$this->name($t);
					if (!isset($this->variables[$varName]))
						return false;
					$base=&$this->variables[$varName];
					foreach ($indexes as $index)
					{
						if (!isset($base[$index]))	
							return false;
						$base=&$base[$index];
					}
					return true;
				}
				else
					$this->error("Unknown node for isset",$var);
			}
			return true;
		}
		elseif ($node instanceof Node\Expr\Eval_)
		{
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
			$realfile=realpath($file);
			if ($type%2==0) //once
				if (isset($this->included_files[$realfile])) return true;
			if (!file_exists($realfile) or !is_file($realfile))
				if ($type<=2) //include
				{
					$this->warning("include({$realfile}): failed to open stream: No such file or directory");
					return false;
				}
				else
				{
					$this->error("require({$realfile}): failed to open stream: No such file or directory");
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
				return $this->new_object($classname,$node->args); //user function
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
	protected function variable_name($node)
	{

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
		elseif ($ast instanceof Node\Expr\ArrayDimFetch)
		{
			#FIXME: this should return the actual variable $x[..][...][...] and not the value!
			// $t=$ast;
			// $dim=0;
			// $indexes=[];
			// //each ArrayDimFetch has a var and a dim. var can either be a variable, or another ArrayDimFetch
			// while ($t instanceof Node\Expr\ArrayDimFetch)
			// {
			// 	$dim++;
			// 	$indexes[]=$this->evaluate_expression($t->dim);
			// 	$t=$t->var;
			// }
			// $indexes=array_reverse($indexes);
			// $varName=$this->name($t);
			// $name=$varName.'['.implode("][",$indexes)."]";
			// return $name;
			return $this->evaluate_expression($ast);
		}
		elseif ($ast instanceof Node\Scalar)
			return $ast->value;
		elseif ($ast instanceof Node\Expr\Variable)
		{
			if (is_string($ast->name))
				return $ast->name;
			else
				return $this->evaluate_expression($ast->name);
		}
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
		}
		else
			$this->error("Can not determine name: ",$ast);
		return $res;		
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
		$this->current_file=$last_file;
		return $res;
	}
	
	function __construct()
	{
		$this->parser = new PhpParser\Parser(new PhpParser\Lexer);
		// $this->traverser     = new PhpParser\NodeTraverser;
    	// $this->traverser->addVisitor(new LiteralExplodeDetector);
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
	protected function run_code($ast)
	{
		//first pass, get all definitions
		foreach ($ast as $node)
		{
			if (0);
			elseif ($node instanceof Node\Stmt\Function_)
			{
				// echo PHP_EOL;
				$name=$this->name($node->name);
				$this->functions[$name]=array("params"=>$node->params,"code"=>$node->stmts,"file"=>$this->current_file); #FIXME: name
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
			if ($this->terminated) return null;
			// echo get_class($node),PHP_EOL;
			if ($node instanceof Node\Stmt\Echo_)
				foreach ($node->exprs as $expr)
					$this->output($this->evaluate_expression($expr));
				// $this->output_array($this->evaluate_expression_array($node->exprs));
			elseif ($node instanceof Node\Stmt\Const_)
				continue;
			elseif ($node instanceof Node\Stmt\Function_)
				continue;
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
				return $this->evaluate_expression($node->expr);
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
							break 2;
						else
							break ;
					}
					if ($this->continue)
					{
						$this->continue--;
						if ($this->continue)
							break 2;
					}
					if ($i>$this->infinite_loop)
					{
						$this->error("Infinite loop");
						break;
					}
				}
				$this->loop--;
			}
			elseif ($node instanceof Node\Stmt\While_)
			{
				$i=0;
				while ($this->evaluate_expression($node->cond))
				{
					$this->run_code($node->stmts);
					$i++;
					if ($this->break)
					{
						$this->break--;
						if ($this->break) //nested break, the 2 here ensures that the rest of statements in current loop don't execute
							break 2;
						else
							break ;
					}
					if ($this->continue)
					{
						$this->continue--;
						if ($this->continue)
							break 2;
					}
					if ($i>$this->infinite_loop)
					{
						$this->error("Infinite loop");
						break;
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
							break 2;
						else
							break ;
					}
					if ($this->continue)
					{
						$this->continue--;
						if ($this->continue)
							break 2;
					}					
					if ($i>$this->infinite_loop)
					{
						$this->error("Infinite loop");
						break;
					}
				}
				while ($this->evaluate_expression($node->cond));
			}
			elseif ($node instanceof Node\Stmt\Foreach_)
			{
				$list=$this->evaluate_expression($node->expr);
				if (isset($node->keyVar))
					$keyVar=$this->name($node->keyVar->name);
				$valueVar=$this->name($node->valueVar->name);
				// $keyVarExists=false;
				// $valueVarExists=false;
				// if (isset($this->variables[$keyVar]))
				// 	$keyVarExists=true;
				// if (isset($this->variables[$valueVar]))
				// 	$valueVarExists=true;
				foreach ($list as $k=>$v)
				{
					if (isset($keyVar))
						$this->variables[$keyVar]=$k;
					$this->variables[$valueVar]=$v;
					$this->run_code($node->stmts);
					if ($this->break)
					{
						$this->break--;
						if ($this->break) //nested break, the 2 here ensures that the rest of statements in current loop don't execute
							break 2;
						else
							break ;
					}
					if ($this->continue)
					{
						$this->continue--;
						if ($this->continue)
							break 2;
					}

				}
				// if (!$valueVarExists)
				// 	unset($this->variables[$valueVar]);
				// if (!$keyVarExists)
				// 	unset($this->variables[$keyVar]);
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
							break 2;
						else
							break ;
					}
					if ($this->continue)
					{
						$this->continue--;
						if ($this->continue)
							break 2;
					}
				}
			} 
			elseif ($node instanceof Node\Stmt\Break_)
			{
				if (isset($node->num))
					$this->break+=$this->evaluate_expression($node->num);
				else
					$this->break++;
				break; //break this loop of run_code, and have the real loop break because $this->break > 0
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

				break ;
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
						$varName=$catch->var;
						if ($e instanceof $type)
						{
							$var=$e;
							$this->variables[$varName]=$var;
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
			elseif ($node instanceof Node\Stmt\InlineHTML)
				$this->output($node->value); //FIXME: u sure this is the only way inline is? just strings?
			elseif ($node instanceof Node\Stmt\Global_)
			{
				foreach ($node->vars as $var)
				{
					$name=$this->name($var);
					$this->variables[$name]=$this->globals()[$name];
				}
			}
			elseif ($node instanceof Node\Expr)
				$this->evaluate_expression($node);
			else
			{
				$this->error("Unknown node type: ",$node);	
			}


		}
	}	
	function __destruct()
	{
	}
}




















$_GET['url']='http://abiusx.com/blog/wp-content/themes/nano2/images/banner.jpg';
$x=new Emulator;
// $x->start("sample-stmts.php");
$x->start("yapig-0.95b/index.php");
echo "Output of size ".strlen($x->output)." was generated.",PHP_EOL;
// var_dump($x->output);
// echo PHP_EOL,"### Variables ###",PHP_EOL;
// var_dump($x->variables);