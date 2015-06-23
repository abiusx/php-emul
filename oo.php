<?php
require_once __DIR__."/main.php";
use PhpParser\Node;
//trait_, instanceof, methodcall,new_,propertyfetch,staticcall,staticpropertyfetch,clone_,staticvar, static_,traituse,namespace,use
class EmulatorObjectProperty
{
	public $name;
	public $value;
	public $visibility;
	function __construct($name,$value,$visibility=Visibility_Public)
	{
		$this->name=$name;
		$this->value=$value;
		$this->visibility=$visibility;
	}

	const Visibility_Public=1;
	const Visibility_Protected=2;
	const Visibility_Private=4;
}
class EmulatorObject
{
	public $properties;
	public $classname;
	public function __construct($classname,$properties)
	{
		$this->classname=$classname;
		$this->properties=$properties;
	}

}
class OOEmulator extends Emulator
{
	public $classes=[];
	protected $current_class,$current_method,$current_trait;
	protected $current_namespace;
	protected $this=null;
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
			$properties=[];
			$static_properties=[];
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
							$visibility=EmulatorObjectProperty::Visibility_Private;
						elseif ($type &2)
							$visibility=EmulatorObjectProperty::Visibility_Protected;
						else
							$visibility=EmulatorObjectProperty::Visibility_Public;

						if ($type & 8 ) //static
							$static_properties[$propname]=new EmulatorObjectProperty($propname,$val,$visibility);
						else
							$properties[$propname]=new EmulatorObjectProperty($propname,$val,$visibility);
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
			$class=(object)["properties"=>$properties,"static"=>$static_properties,"consts"=>$consts,"methods"=>$methods,'parent'=>$extends,'interfaces'=>$interfaces,'type'=>$classtype,'file'=>$this->current_file];
			$this->classes[$classname]=$class;
			// echo $classname,":";print_r($class);
		}
		else
			parent::get_declarations($node);

	}

	protected function new_object($classname,array $args)
	{
		if (array_key_exists($classname, $this->classes))
		{
			#TODO: bring properties of all parents too
			$obj=new EmulatorObject($classname,$this->classes[$classname]->properties);
			foreach ($this->ancestry($classname,true) as $class)
			{
				foreach ($this->classes[$class]->properties as $property_name=>$property)
					// echo "Setting property {$property_name} from {$class}...",PHP_EOL;	
					$obj->properties[$property_name]=$property;
			}
			// echo $classname,":";
			// print_r($obj->properties);
			foreach ($this->ancestry($classname) as $class)
			{
				if ($this->method_exists($class, "__construct"))
				{
					$this->run_method($obj,"__construct",$args);
					break;
				}
				elseif ($this->method_exists($class,$class))
				{
					$this->run_method($obj,$class,$args);
					break;
				}
			}
			return $obj;
		}
		$this->error("Class '{$classname}' not found ");
	}
	protected function method_exists($class_name,$method_name)
	{
		if (!isset($this->classes[$class_name])) return false;
		return array_key_exists($method_name, $this->classes[$class_name]->methods);
	}
	protected function run_static_method($class_name,$method_name,$args)
	{
		if ($this->verbose)
			echo "\tRunning {$object->classname}::{$name}()...",PHP_EOL;
		$last_file=$this->current_file;
		$last_method=$this->current_method;
		$last_class=$this->current_class;
		$this->current_method=$method_name;
		$this->current_file=$this->classes[$class_name]->file;
		$this->current_class=$class_name;
		$flag=false;
		foreach ($this->ancestry($class_name) as $class)
		{
			if ($this->method_exists($class,$method_name))
			{
				$res=$this->run_sub($this->classes[$class]->methods[$method_name],$args);
				$flag=true;
				break;	
			}

		}
		if (!$flag)
				$this->error("Call to undefined method {$class_name}::{$method_name}()");
		$this->current_method=$last_method;
		$this->current_file=$last_file;
		$this->current_class=$last_class;

		if ($this->return)
			$this->return=false;	
		return $res;
	}
	protected function run_method(&$object,$method_name,$args)
	{
		$class_name=$object->classname;
		$old_this=$this->this;
		$this->this=&$object;

		$res=$this->run_static_method($class_name,$method_name,$args);

		$this->this=&$old_this;
		return $res;
	}

	protected function evaluate_expression($node)
	{
		$this->current_node=$node;
		if (false)
			;
		elseif ($node instanceof Node\Expr\MethodCall)
		{
			$object=&$this->variables[$this->name($node->var)];
			$method_name=$this->name($node->name);
			$args=$node->args;
			return $this->run_method($object,$method_name,$args);
		}
		elseif ($node instanceof Node\Expr\StaticCall)
		{
			$method_name=$this->name($node->name);
			if ($node->class instanceof Node\Expr\Variable)
				$class=$this->evaluate_expression($node->class)->classname;
			elseif ($node->class instanceof Node\Name)
				$class=$this->name($node->class);
			else
				$this->error("Unknown class when calling static function {$method_name}",$node);
			$args=$node->args;
			return $this->run_static_method($class,$method_name,$args);
		}
		elseif ($node instanceof Node\Expr\PropertyFetch)
		{
			$var=&$this->variables[$this->name($node->var)];
			$property_name=$this->name($node->name);
			#FIXME: redo properties
		}
		elseif ($node instanceof Node\Expr\StaticPropertyFetch)
		{
			// print_r($node);
			$classname=$this->name($node->class);
			$property_name=$this->name($node->name);
			if ($this->ancestry($classname))
			{
				foreach($this->ancestry($classname)  as $class)
				{
					if (isset($this->classes[$class]->static[$property_name]))
						return $this->classes[$class]->static[$property_name]->value; #TODO: check for visibility
				}
				$this->error("Access to undeclared static property: {$classname}::${$property_name}");
			}
			else
				$this->error("Class '{$classname}' not found");

		}
		else
			return parent::evaluate_expression($node);

	}	
	/**
	 * Returns all parents, including self, of a class, ordered from youngest
	 * Looks up self and static keywords
	 * @param  [type] $classname [description]
	 * @return [type]            [description]
	 */
	protected function ancestry($classname,$top_to_bottom=false)
	{
		if ($classname==="self")
			$classname=$this->current_class;
		elseif ($classname==="static")
			$classname=$this->current_class;
		elseif ($classname==="parent")
			$classname=$this->current_class->parent;
		
		if (!isset($this->classes[$classname])) return null;
		$res=[$classname];
		while ($this->classes[$classname]->parent)
		{
			$classname=$this->classes[$classname]->parent;
			$res[]=$classname;
		}
		if ($top_to_bottom) $res=array_reverse($res);
		return $res;
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
	protected function name($node)
	{
		if ($node instanceof Node\Expr\PropertyFetch)
		{
			$var=$this->name($node->var);
			$property=$this->name($node->name);
			#FIXME: instead of name, have this return an instance.
			print_r($node);
		}
		else
			return parent::name($node);
	}
}

$x=new OOEmulator;
// $x->start("yapig-0.95b/index.php");
$x->start("sample-oo.php");
echo "Output of size ".strlen($x->output)." was generated:",PHP_EOL;
var_dump(substr($x->output,-100));