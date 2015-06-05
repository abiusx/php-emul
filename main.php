<?php
require_once __DIR__."/PHP-Parser/lib/bootstrap.php";
use PhpParser\Node;
class MyNodeVisitor extends  PhpParser\NodeVisitorAbstract
{
	public static $result=[];
	public static $file;
}

class Emulator
{	
	protected $last_node,$last_file;
	public $output;
	public $variables=[];
	public $functions=[];
	public $parser;
	public $variable_stack=[];
	function error_handler($errno, $errstr, $errfile, $errline)
	{
		$file=$errfile;
		if (isset($this->last_file)) $file=$this->last_file;
		$line=$errline;;
		if (isset($this->last_node)) $line=$this->last_node->getLine();
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
		echo "PHP-Emul {$str}:  {$errstr} in {$file} on line {$line}",PHP_EOL;
		// if ($fatal) die();
		return true;
	}
	function error($msg)
	{
		trigger_error($msg);
	}
	function output_array(array $args)
	{
		$this->output.=implode("",$args);
	}
	function output()
	{
		$args=func_get_args();
		$this->output.=implode("",$args);
	}
	protected function evaluate_expression_array(array $ast)
	{
		$out=[];
		foreach ($ast as $element)
			$out[]=$this->evaluate_expression($element);
		return $out;
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
		$this->push();
		$function=$this->functions[$name];
		if (count($function['params'])!=count($args))
			$this->error("{$name} expects ".count($function['params'])." arguments but received ".count($args));
		reset($args);
		foreach ($function['params'] as $param)
		{
			$this->variables[$param->name]=current($args);
			next($args);
		}
		$res=$this->run_code($function['code']);

		$this->pop();
		return $res;
	}
	protected function evaluate_expression($ast)
	{
		$node=$ast;
		$this->last_node=$node;
		if (false);
		elseif ($node instanceof Node\Expr\FuncCall)
		{
			$name=$this->name($node->name);
			$args=[];
			foreach ($node->args as $arg)
				$args[]=$this->evaluate_expression($arg->value);
			if (isset($this->functions[$name]))
				return $this->run_function($name,$args); //user function
			else
				return call_user_func_array($name,$args); //core function
			// die("Yoyo");
		}
		elseif ($node instanceof Node\Expr\Assign)
			return $this->variables[$node->var->name]=$this->evaluate_expression($node->expr);
		elseif ($node instanceof Node\Expr\Cast)
		{
			if ($node instanceof Node\Expr\Cast\Int_)
				return (int)$this->evaluate_expression($node->expr);
			else
			{
				echo "Unknown cast: ";
				print_r($node);
			}
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
			
			elseif ($node instanceof Node\Expr\BinaryOp\Concat)
				return $this->evaluate_expression($node->left).$this->evaluate_expression($node->right);

			else
			{
				echo "Unknown binary op: ";
				print_r($node);
			}
		}
		elseif ($node instanceof Node\Scalar)
		{
			if ($node instanceof Node\Scalar\String)
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
			else
			{
				echo "Unknown scalar node: ";
				print_r($node);
			}
		}
		elseif ($node instanceof Node\Expr\ArrayDimFetch)
		{
			$name=$node->var->name;
			$dim=$this->evaluate_expression($node->dim);
			if (!isset($this->variables[$name]))
				$this->error("Undefined array \${$name}");
			elseif (!isset($this->variables[$name][$dim]))
				$this->error("Undefined array index \${$name}[{$dim}]");
			else
				return $this->variables[$name][$dim];

		}
		elseif ($node instanceof Node\Expr\Variable)
		{
			if (isset($this->variables[$node->name]))
				return $this->variables[$node->name];
			else
				$this->error("Undefined variable {$node->name}");
		}
		elseif ($node instanceof Node\Expr\ConstFetch)
		{
			$name=$this->name($node->name);
			if (defined($name))
				return constant($name);
			else
				$this->error("Undefined constant {$name}");

		}
		else
		{
			echo "Unknown expression node: ",
			print_r($node);
		}
		return null;
	}
	protected function name($ast)
	{
		if (is_string($ast))
			return $ast;
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
	public function run_file($file)
	{
		$this->last_file=realpath($file);
		$code=file_get_contents($file);
		$ast=$this->parser->parse($code);
		return $this->run_code($ast);
	}
	protected function run_code($ast)
	{
		foreach ($ast as $node)
		{
			// echo get_class($node),PHP_EOL;
			if ($node instanceof Node\Stmt\Echo_)
				$this->output_array($this->evaluate_expression_array($node->exprs));
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
			elseif ($node instanceof Node\Stmt\Function_)
			{
				$name=$this->name($node->name);
				$this->functions[$name]=array("params"=>$node->params,"code"=>$node->stmts);
				// print_r($node);
			}
			elseif ($node instanceof Node\Stmt\Return_)
				return $this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\FuncCall)
				$this->evaluate_expression($node); //function call without return value used
			elseif ($node instanceof Node\Expr\Exit_)
				return $this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\Assign)
				$this->evaluate_expression($node);
			else
			{
				echo "Unknown node type: ";	
				print_r($node);
			}


		}
	}
	function __construct()
	{
		$this->parser = new PhpParser\Parser(new PhpParser\Lexer);
		$this->traverser     = new PhpParser\NodeTraverser;
    	$this->traverser->addVisitor(new LiteralExplodeDetector);
    	$this->init();
	}
	function init()
	{
		$this->variables['_GET']=isset($_GET)?$_GET:array();
		$this->variables['_POST']=isset($_POST)?$_POST:array();

	}
	function start($file)
	{
		set_error_handler(array($this,"error_handler"));
		$res=$this->run_file($file);
		restore_error_handler();

		return $res;
	}
}





















class LiteralExplodeDetector extends MyNodeVisitor
{
    public function leaveNode_(Node $node) {
        if ($node instanceof Node\Scalar\String_) {
            $node->value = 'foo';
        }
        if ($node instanceof Node\Name) {
            return new Node\Name($node->toString('_'));
        } elseif ($node instanceof Stmt\Class_
                  || $node instanceof Stmt\Interface_
                  || $node instanceof Stmt\Function_) {
            $node->name = $node->namespacedName->toString('_');
        } elseif ($node instanceof Stmt\Const_) {
            foreach ($node->consts as $const) {
                $const->name = $const->namespacedName->toString('_');
            }
        } elseif ($node instanceof Stmt\Namespace_) {
            // returning an array merges is into the parent array
            return $node->stmts;
        } elseif ($node instanceof Stmt\Use_) {
            // returning false removed the node altogether
            return false;
        }
    }
    public function beforeTraverse(array $nodes) {}
	public function enterNode(PhpParser\Node $node){}
	public function leaveNode(PhpParser\Node $node)
	{
		if ($node instanceof Node\Expr\FuncCall)
		{
			if ($node->name=="explode" and $node->args[0]->value instanceof Node\Scalar\String_)
			{
				// print_r($node);
				self::$result[]=array(
					"function"=>$node->name->__toString(),
					"value"=>$node->args[0]->value->value,
					"line"=>$node->args[0]->value->getLine(),
					"file"=>self::$file
					);
				// print_r($this->result);
			}
		}
	}
	public function afterTraverse(array $nodes){}
}

function analyze($dir,$visitor)
{
	ini_set("memory_limit",-1);
	$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
	$parser = new PhpParser\Parser(new PhpParser\Lexer);
    $traverser     = new PhpParser\NodeTraverser;
    $traverser->addVisitor(new $visitor);
	$str="Parsing all files in {$dir}";
	$count=strlen($str);
	echo $str;
	$statements=[];
	foreach ($files as $k=>$file)
	{
		if ($file=="." or $file=="..") continue;
		$fileinfo = pathinfo($file);
		if (isset($fileinfo['extension']) and $fileinfo['extension']!=="php") continue;
		echo ".";
		$count++;
		if ($count%80==0) echo PHP_EOL;
		$code=file_get_contents($file);	
		try {
			$visitor::$file=$file->__toString();
		    $statements[$k] = $parser->parse($code);
		    $statements[$k] = $traverser->traverse($statements[$k]);

		    // $statements is an array of statement nodes
		} catch (PhpParser\Error $e) {
		    echo 'Parse Error: ', $e->getMessage()," in file {$file}",PHP_EOL;
		}
		// print_r($statements);
	}
	echo PHP_EOL;
	printf("Memory Usage: %.2fKB\n",memory_get_usage()/1024);
	printf("Parsed a total of %d files\n",$count);
	// echo memory_get_usage()/1024,"KB,",memory_get_peak_usage()/1024,"KB",PHP_EOL;
	return $statements;
}
$_GET['name']='123';
$_GET['str']='123';
$_GET['q']='123';
$x=new Emulator;
$x->start("sample.php");
var_dump($x->output);
echo PHP_EOL,"### Variables ###",PHP_EOL;
var_dump($x->variables);