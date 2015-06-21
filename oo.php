<?php
require_once __DIR__."/main.php";
use PhpParser\Node;
//trait_, instanceof, methodcall,new_,propertyfetch,staticcall,staticpropertyfetch,clone_,staticvar, static_,traituse,namespace,use
class EmulatorObject
{
	public $properties;
}
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
			$classtype=null;
			if (isset($node->type))
				$classtype=$node->type;
			$classname=$this->name($node->name);
			$extends=null;
			if ($node->extends)
				$extends=$this->name($node->extends);
			$interfaces=[];
			$consts=[];
			$methods=[];
			$properties=(object)["public"=>new stdClass,"private"=>new stdClass,"protected"=>new stdClass];
			$properties->static=clone $properties;
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
							$properties->static->$visibility->$propname=$val;
						else
							$properties->$visibility->$propname=$val;
					}
				}
				elseif ($part instanceof Node\Stmt\ClassMethod)
				{
					$methodname=$this->name($part->name);
					$type=$part->type;
					$methods[$methodname]=(object)array('name'=>$methodname,"params"=>$part->params,"code"=>$part->stmts,"file"=>$this->current_file,'type'=>$type); 
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
			$class=(object)["properties"=>$properties,"consts"=>$consts,"methods"=>$methods,'parent'=>$extends,'interfaces'=>$interfaces,'type'=>$classtype];
			$this->classes[$classname]=$class;
			// echo $classname,":";print_r($class);
		}
		else
			parent::get_declarations($node);

	}
	protected function evaluate_expression($node)
	{
		$this->current_node=$node;
		if (false)
			;
		else
			return parent::evaluate_expression($node);

	}
	protected function new_object($name,array $args)
	{
		if (array_key_exists($name, $this->classes))
		{
			$this->variables[$name]=new EmulatorObject();
			$this->variables[$name]->properties=$this->classes[$name]->properties;
			if ($this->method_exists($name, "__construct"))
				$this->run_method($name,"__construct",$args);
			elseif ($this->method_exists($name,$name))
				$this->run_method($name,$name,$args);
		}
	}
	protected function method_exists($class_name,$method_name)
	{
		if (!isset($this->classes[$class_name])) return false;
		foreach ($this->classes[$class_name]->methods as $method)
			if ($method->name===$method_name)
				return true;
		return false;
	}
	protected function run_method($object_name,$method_name,$args)
	{

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