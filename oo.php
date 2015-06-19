<?php
require_once __DIR__."/main.php";
use PhpParser\Node;
//trait_, instanceof, methodcall,new_,propertyfetch,staticcall,staticpropertyfetch,clone_,staticvar, static_,traituse,namespace,use
class OOEmulator extends Emulator
{
	public $classes=[];
	protected function get_declarations($node)
	{
		if ($node instanceof Node\Stmt\ClassLike)
		{
			//has type, implements (array), stmts (Array), name, extends
			//type=0 is normal, type=16 is abstract
			// print_r($node);
			$type=null;
			if (isset($node->type))
				$type=$node->type;
			$classname=$this->name($node->name);
			$extends=null;
			if ($node->extends)
				$extends=$this->name($node->extends);
			$interfaces=[];
			$consts=[];
			$methods=[];
			$properties=["public"=>[],"private"=>[],"protected"=>[]];
			$properties['static']=$properties;
			foreach ($node->stmts as $part)
			{
				if ($part instanceof Node\Stmt\Property)
				{
					$type=$part->type; //1= public, 2=protected, 4=private, 8= static
					foreach ($part->props as $property)	
					{
						$propname=$this->name($property->name);
						if ($property->default)
							$val=$this->evaluate_expression($property->default);
						else
							$val=NULL;
						if ($type & 4)
							$visibility="private";
						elseif ($type &2)
							$visibility="protected";
						else
							$visibility="public";

						if ($type & 8 ) //static
							$properties['static'][$visibility][$propname]=$val;
						else
							$properties[$visibility][$propname]=$val;
					}
				}
				elseif ($part instanceof Node\Stmt\ClassMethod)
				{
					$methodname=$this->name($part->name);
					$methods[$methodname]=array("params"=>$part->params,"code"=>$part->stmts,"file"=>$this->current_file); 
				}
				elseif ($part instanceof Node\Stmt\ClassConst)
				{
					foreach ($part->consts as $const)
					{
						$constname=$this->name($const->name);
						$val=$this->evaluate_expression($const->value);
						$consts[$constname]=$val;
					}
				}
				else
					$this->error("Unknown class part for class '{$classname}'",$part);

			}
			if (isset($node->implements))
			foreach ($node->implements as $interface)
				$interfaces[]=$this->name($interface);
			$class=["properties"=>$properties,"consts"=>$consts,"methods"=>$methods,'parent'=>$extends,'interfaces'=>$interfaces,'type'=>$type];
			$this->classes[$classname]=$class;
			// echo $classname,":";print_r($class);
		}
		else
			parent::get_declarations($node);

	}
	protected function run_statement($node)
	{
		if (0)
			;
		elseif ($node instanceof Node\Stmt\ClassLike)
			return;
		else
			parent::run_statement($node);
	}

}

$x=new OOEmulator;
// $x->start("yapig-0.95b/index.php");
$x->start("sample-oo.php");
echo "Output of size ".strlen($x->output)." was generated.",PHP_EOL;
var_dump(substr($x->output,-100));