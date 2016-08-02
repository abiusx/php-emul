<?php
use PhpParser\Node;
/**
 * Evaluates an expression for the emulator
 */
trait EmulatorExpression {

	
	protected function expression_preprocess($node)
	{
		$this->current_node=$node;
		if (is_object($node) and method_exists($node, "getLine") and $node->getLine()!=$this->current_line)
		{
			$this->current_line=$node->getLine();
			$this->verbose("Line {$this->current_line} (expression)".PHP_EOL,4);
		}	
	}
	/**
	 * Evaluate all nodes of type Node\Expr and return appropriate value
	 * This is the core of the emulator/interpreter.
	 * @param  Node $ast Abstract Syntax Tree node
	 * @return mixed      value
	 */
	protected function evaluate_expression($node)
	{
		if ($this->terminated) return null;
		$this->expression_preprocess($node);		
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
			$this->variable_set_byref($node->var,$originalVar);
			// $var=$originalVar;
		}
		elseif ($node instanceof Node\Expr\Assign)
		{
			if ($node->var instanceof Node\Expr\List_) //list(x,y)=f()
			{
				$resArray=$this->evaluate_expression($node->expr);
				// 	PHP's list uses numeric iteration over the resArray. 
				// 	This means that list($a,$b)=[1,'a'=>'b'] will error "Undefined offset: 1"
				// 	because it wants to assign $resArray[1] to $b. Thus we use index here.
				$index=0;
				$outArray=[];
				foreach ($node->var->vars as $var)
				{
					if (!isset($resArray[$index]))
						$this->notice("Undefined offset: {$index}");
					if ($var===null)
						$outArray[]=$resArray[$index++];
					else
						$outArray[]=$this->variable_set($var,$resArray[$index++]);
				}
				//return the rest of offsets, they are not assigned to anything by list, but still returned.
				while ( $index<count($resArray))
				{
					if (!isset($resArray[$index]))
						$this->notice("Undefined offset: {$index}");
					$outArray[]=$resArray[$index++];
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
			{
				if (isset($item->key))
				{
					$key=$this->evaluate_expression($item->key);
					if ($item->byRef)
						$out[$key]=&$this->variable_reference($item->value);
					else
						$out[$key]=$this->evaluate_expression($item->value);
				}
				else
					if ($item->byRef)
						$out[]=&$this->variable_reference($item->value);
					else
						$out[]=$this->evaluate_expression($item->value);
			}
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
			
			$l=$this->evaluate_expression($node->left); #can't eval right here, prevents short circuit reliant code
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
			$this->verbose(sprintf("Terminated at %s:%d.\n",substr($this->current_file,strlen($this->folder)),$this->current_line));
			if (isset($node->expr))
			{
				$res=$this->evaluate_expression($node->expr);	
				if (!is_int($res))
					$this->output($res);
				else
					$this->termination_value=$res;
			}
			else
				$res=null;

			$this->terminated=true;	
			return $res;
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
			$names=[null,'include','include_once','require','require_once'];
			$name=$names[$type];
			$file=$this->evaluate_expression($node->expr);
			
			$realfile =realpath(dirname($this->current_file)."/".$file); //first check the directory of the file using include (as per php)
			if (!file_exists($realfile) or !is_file($realfile)) //second check current dir
				$realfile=realpath($file);
			if ($type%2==0) //once
				if (isset($this->included_files[$realfile])) return true;
			if (!file_exists($realfile) or !is_file($realfile))
				if ($type<=2) //include
				{
					$this->warning("{$name}({$file}): failed to open stream: No such file or directory");
					return false;
				}
				else
				{
					$this->error("{$name}({$file}): failed to open stream: No such file or directory");
					return false;
				}
			array_push($this->trace, (object)array("type"=>"","function"=>$name,"file"=>$this->current_file,"line"=>$this->current_line,
				"args"=>[$realfile]));
			$r=$this->run_file($realfile);
			array_pop($this->trace);
			return $r;
		}
		elseif ($node instanceof Node\Expr\Ternary)
		{
			if ($this->evaluate_expression($node->cond)) return $this->evaluate_expression($node->if);
			else return $this->evaluate_expression($node->else);
		}
		else
			$this->error("Unknown expression node: ",$node);
		return null;
	}
}