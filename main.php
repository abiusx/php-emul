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
	function error_handler($errno, $errstr, $errfile, $errline)
	{
		$file=isset($this->last_file)?$this->last_file:$errfile;
		$line=isset($this->last_node)?$this->last_node->getLine():$errline;
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
			return call_user_func_array($name,$args);
			// die("Yoyo");
		}
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
	public $variables;
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
			elseif ($node instanceof Node\Expr\FuncCall)
				$this->evaluate_expression($node); //function call without return value used
			elseif ($node instanceof Node\Expr\Exit_)
				return $this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Expr\Assign)
				$this->variables[$node->var->name]=$this->evaluate_expression($node->expr);
			else
			{
				echo "Unknown node type: ";	
				print_r($node);
			}


		}
	}
	public $parser;
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
echo "Variables: ",PHP_EOL;
var_dump($x->variables);