<?php
require_once __DIR__."/emulator.php";
use PhpParser\Node;
function apache_getenv()
{
	return "";
}
//trait_,traituse,namespace,use
//TODO: magic methods, destructor
//TODO: internal classes, methods and etc.
//FIXME: static method calls using call_user_func* fail, 
//	e.g wordpress/wp-includes/class-http:324 			if ( !call_user_func( array( $class, 'test' ), $args, $url ) )

class EmulatorObject
{
	const Visibility_Public=1;
	const Visibility_Protected=2;
	const Visibility_Private=4;
	/**
	 * Array of keys as propname, values as EmulatorObjectProperty
	 * @var [type]
	 */
	public $properties;
	/**
	 * Visibility of properties
	 * @var [type]
	 */
	public $visibilities;
	/**
	 * @var string
	 */
	public $classname;
	public function __construct($classname,$properties=[],$visibilities=[])
	{
		$this->classname=$classname;
		$this->properties=$properties;
		$this->visibilities=$visibilities;
	}

}
class OOEmulator extends Emulator
{
	function __construct()
	{
		parent::__construct();
		// $this->this_hack=array('this'=>&$this->this);
	}
	public $classes=[];
	protected $current_class,$current_method,$current_trait;
	protected $current_namespace;
	protected $this=null,$self=null;

	protected function get_declarations($node)
	{
		if ($node instanceof Node\Stmt\ClassLike)
		{
			//has type, implements (array), stmts (Array), name, extends
			//type=0 is normal, type=16 is abstract
			
			$classtype=null;
			if (isset($node->type))
				$classtype=$node->type;
			$classname=$this->name($node->name);
			$this->classes[strtolower($classname)]=new stdClass;
			$class=&$this->classes[strtolower($classname)];

			$extends=null;
			if ($node->extends)
				$extends=$this->name($node->extends);
			
			$class->interfaces=[];
			$class->consts=[];
			$class->methods=[];
			$class->properties=[];
			$class->visibilities=[];
			$class->static=[];
			$class->parent=$extends;
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
							$visibility=EmulatorObject::Visibility_Private;
						elseif ($type &2)
							$visibility=EmulatorObject::Visibility_Protected;
						else
							$visibility=EmulatorObject::Visibility_Public;

						if ($type & 8 ) //static
						{
							$class->static[$propname]=$val;
							$class->static_visibility[$propname]=$visibility;
						}
						else
							$class->properties[$propname]=$val;
							$class->visibilities[$propname]=$visibility;
					}
				}
				elseif ($part instanceof Node\Stmt\ClassMethod)
				{
					$methodname=$this->name($part->name);
					$type=$part->type;
					$class->methods[$methodname]=(object)array('name'=>$methodname,"params"=>$part->params,"code"=>$part->stmts,"file"=>$this->current_file,'type'=>$type,'statics'=>[]); 
				}
				elseif ($part instanceof Node\Stmt\ClassConst)
				{
					foreach ($part->consts as $const)
					{
						$constname=$this->name($const->name);
						$val=$this->evaluate_expression($const->value);
						$class->consts[$constname]=$val;
					}
				}
				else
					$this->error("Unknown class part for class '{$classname}'",$part);

			}
			$interfaces=[];
			if (isset($node->implements))
			foreach ($node->implements as $interface)
				$interfaces[]=$this->name($interface);
			$class->type=$classtype;
			$class->file=$this->current_file;
			$class->interfaces=$interfaces;
			// $class=(object)["properties"=>$properties,"static"=>$static_properties,"consts"=>$consts,"methods"=>$methods,'parent'=>$extends,'interfaces'=>$interfaces,'type'=>$classtype,'file'=>$this->current_file];
			// $this->classes[$classname]=$class;
		}
		else
			parent::get_declarations($node);

	}
	protected function new_user_object($classname,array $args)
	{
		$this->verbose("Creating object of type {$classname}...".PHP_EOL,2);
		$obj=new EmulatorObject($classname,$this->classes[strtolower($classname)]->properties,$this->classes[strtolower($classname)]->visibilities);
		foreach ($this->ancestry($classname,true) as $class)
		{
			foreach ($this->classes[strtolower($class)]->properties as $property_name=>$property)
				$obj->properties[$property_name]=$property;
		}
		foreach ($this->ancestry($classname) as $class)
		{
			if ($this->user_method_exists($class, "__construct"))
			{
				$this->run_user_method($obj,"__construct",$args);
				break;
			}
			elseif ($this->user_method_exists($class,$class))
			{
				$this->run_user_method($obj,$class,$args);
				break;
			}
		}
		return $obj;
	}
	protected function new_core_object($classname,array $args)
	{
		$argValues=[];
		foreach ($args as $arg)
			$argValues[]=$this->evaluate_expression($arg->value);
		
		ob_start();	
		$r = new ReflectionClass($classname);
		$ret = $r->newInstanceArgs($argValues); #TODO: byref?
		// $ret=new $classname($argValues); //core class
		$output=ob_get_clean();
		$this->output($output);
		return $ret;
	}
	protected function new_object($classname,array $args)
	{

		if (array_key_exists(strtolower($classname), $this->classes)) //user classes
			return $this->new_user_object($classname,$args);
		elseif (class_exists($classname)) //core classes
			return $this->new_core_object($classname,$args);
		else
			$this->error("Class '{$classname}' not found ");
	}
	protected function user_method_exists($class_name,$method_name)
	{
		if (!isset($this->classes[strtolower($class_name)])) return false;
		return array_key_exists($method_name, $this->classes[strtolower($class_name)]->methods);
	}
	protected function run_static_method($original_class_name,$method_name,$args)
	{
		$class_name=$this->real_class($original_class_name);
		if (array_key_exists(strtolower($class_name), $this->classes))
			return $this->run_user_static_method($original_class_name,$method_name,$args);
		elseif (class_exists($class_name))
			return call_user_func_array($class_name."::".$method_name, $args);
		else
			$this->error("Can not call static method '{$class_name}::{$method_name}', class '{$original_class_name}' does not exist.\n");

	}
	protected function run_user_static_method($original_class_name,$method_name,$args)
	{
		$class_name=$this->real_class($original_class_name);
		if ($this->verbose)
			$this->verbose("\tRunning {$class_name}::{$method_name}()...".PHP_EOL,2);
		$last_method=$this->current_method;
		$last_class=$this->current_class;
		$this->current_method=$method_name;
		$this->current_class=$class_name;
		$flag=false;
		foreach ($this->ancestry($class_name) as $class)
		{
			if ($this->user_method_exists($class,$method_name))
			{
				$last_self=$this->self;
				$this->self=$class;
				array_push($this->trace, (object)array("type"=>"method","name"=>$method_name,"class"=>$class,"file"=>$this->current_file,"line"=>$this->current_line));
				$last_file=$this->current_file;
				$this->current_file=$this->classes[strtolower($class_name)]->file;
				$res=$this->run_function($this->classes[strtolower($class)]->methods[$method_name],$args);
				array_pop($this->trace);
				$this->self=$last_self;
				$flag=true;
				break;	
			}

		}
		if (!$flag)
		{
			$this->error("Call to undefined method {$class_name}::{$method_name}()");
			$res=null;
		}
		$this->current_method=$last_method;
		$this->current_file=$last_file;
		$this->current_class=$last_class;

		if ($this->return)
			$this->return=false;	
		return $res;
	}
	protected function run_method(&$object,$method_name,$args)
	{
		if ($object instanceof EmulatorObject)
			return $this->run_user_method($object,$method_name,$args);
		elseif (is_object($object))
			return call_user_func_array(array($object,$method_name), $args);
		else
			$this->error("Can not call method '{$method_name}' on a non-object.\n",$object);
	}
	protected function run_user_method(&$object,$method_name,$args)
	{
		if (!($object instanceof EmulatorObject))
		{
			$this->error("Inconsistency in object oriented emulation. A malformed object detected.",$object);
			return null;
		}
		$class_name=$object->classname;
		$old_this=$this->this;
		$this->this=&$object;
		
		$res=$this->run_user_static_method($class_name,$method_name,$args);

		$this->this=&$old_this;
		return $res;
	}

	protected function evaluate_expression($node)
	{
		$this->current_node=$node;
		if (false)
			;
		elseif ($node instanceof Node\Expr\New_)
		{

			$classname=$this->name($node->class);
			return $this->new_object($classname,$node->args); //user function

		}
		elseif ($node instanceof Node\Expr\MethodCall)
		{
			$object=&$this->variable_reference($node->var);
			$method_name=$this->name($node->name);
			$this->verbose("Method call ".$object->classname."::".$method_name,3);
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
			$var=&$this->variable_reference($node);
			return $var;
		}
		elseif ($node instanceof Node\Expr\StaticPropertyFetch)
		{
			$var=&$this->variable_reference($node); //do not create the property in static
			return $var;
		}
		elseif ($node instanceof Node\Expr\ClassConstFetch)
		{
			$class=$this->name($node->class);
			$constant=$this->name($node->name);
			foreach ($this->ancestry($class) as $cls)
			{
				if (array_key_exists($constant, $this->classes[strtolower($cls)]->consts))
					return $this->classes[strtolower($cls)]->consts[$constant];
			}
			$this->error("Undefined class constant '{$constant}'",$node);
		}
		elseif ($node instanceof Node\Expr\Clone_)
		{
			$var=$this->variable_get($node->expr);
			$var2=clone $var;
			// $var2->properties=[];
			// foreach ($var->properties as $k=>$property)
			// 	$var2->properties[$k]=clone $property;
			return $var2;
		}
		elseif ($node instanceof Node\Expr\Instanceof_)
		{
			$var=$this->evaluate_expression($node->expr);
			$classname=$this->name($node->class);
			if ($var instanceof EmulatorObject)
			{
				foreach ($this->ancestry($var->classname) as $class)
					if ($class===$classname) return true;
				return false;
			}
			else
				return $var instanceof $classname;
		}		
		elseif ($node instanceof Node\Expr\Cast\Object_)
				return $this->to_object($this->evaluate_expression($node->expr));
		else
			return parent::evaluate_expression($node);

	}	
	/**
	 * Converts any value to object
	 * @param  mixed $val [description]
	 * @return EmulatorObject      [description]
	 */
	protected function to_object($val)
	{
		$obj=new EmulatorObject("stdClass");
		if (is_array($val))
		{
			foreach ($val as $k=>$v)
			{
				$obj->properties[$k]=$v;
				$obj->visibilities[$k]=EmulatorObject::Visibility_Public;
			}
		}
		else
		{
			$obj->properties['scalar']=$val;
			$obj->visibilities['scalar']=EmulatorObject::Visibility_Public;
		}

		return $obj;
	}
	protected function real_class($classname)
	{
		if ($classname==="self")
			$classname=$this->self;
		elseif ($classname==="static")
			$classname=$this->current_class;
		elseif ($classname==="parent")
			$classname=$this->classes[strtolower($this->current_class)]->parent;	

		return $classname;
	}
	/**
	 * Returns all parents, including self, of a class, ordered from youngest
	 * Looks up self and static keywords
	 * @param  [type] $classname [description]
	 * @return [type]            [description]
	 */
	protected function ancestry($classname,$top_to_bottom=false)
	{
		$classname=$this->real_class($classname);
		if (!isset($this->classes[strtolower($classname)])) return null;
		$res=[$classname];
		while ($this->classes[strtolower($classname)]->parent)
		{
			$classname=$this->classes[strtolower($classname)]->parent;
			$res[]=$classname;
		}
		if ($top_to_bottom) $res=array_reverse($res);
		return $res;
	}
	function name($node)
	{
		return parent::name($node);
	}
	protected function run_statement($node)
	{
		if (0)
			;
		elseif ($node instanceof Node\Stmt\ClassLike)
			return;
		elseif ($node instanceof Node\Stmt\Static_)
		{
			//TODO: bind this static variable to the method being runned in the class it belongs to
			if (end($this->trace)->type=="method" and  $this->user_method_exists(end($this->trace)->class,end($this->trace)->name)) //statc inside a method)
			{
				$class=end($this->trace)->class;
				$method=end($this->trace)->name;

				//TODO
				$statics=&$this->classes[strtolower($class)]->methods[$method]->statics;// &$this->functions[$this->current_function]->statics;
				foreach ($node->vars as $var)
				{
					$name=$this->name($var->name);
					if (!array_key_exists($name,$statics))
						$statics[$name]=$this->evaluate_expression($var->default);
					$this->variables[$name]=&$statics[$name];
				}
			}
			else
				parent::run_statement($node); //static variable in a function
		}		
		else
			parent::run_statement($node);
	}

	protected function &symbol_table($node,&$key,$create=true)
	{
		if ($node instanceof Node\Expr\PropertyFetch)
		{
			$base=&$this->symbol_table($node->var,$key2,$create);
			if ($key2===null)
			{
				$this->error("Variable not found: {$node->var}",$node->var);	
				return $this->null_reference($key);
			}
			$var=&$base[$key2];
			if ($var instanceof EmulatorObject)
			{
				$property_name=$this->name($node->name);
				if (!array_key_exists($property_name, $var->properties))
				{
					if (!$create)
					{
						$this->notice("Undefined property: {$var->classname}::\${$property_name}");
						return $this->null_reference($key);
					}
					else //dynamic properties, on all classes (FIXME: only notice if not stdClass?)
						$var->properties[$property_name]=null;
				}
				$key=$property_name;
				return $var->properties; //reference its value only!
			}
			elseif(is_object($var))
			{
				$property_name=$this->name($node->name);
				$this->verbose(sprintf("Fetching object property: %s::%s\n",get_class($var),$property_name));
				// if (!isset($var->{$property_name}))
				// 	$this->notice("Undefined property: ".get_class($var)."::\${$property_name}");
				return $var->{$property_name}; //self notice? #TEST
			}
			else 
			{
				$this->error("Trying to get property of non-object",$var);
				return $this->null_reference($key);
			}
		}
		elseif ($node instanceof Node\Expr\StaticPropertyFetch)
		{
			$classname=$this->name($node->class);
			if ($classname instanceof EmulatorObject) //support for $object::static_method
				$classname=$classname->classname;
			$property_name=$this->name($node->name);
			if ($this->ancestry($classname))
			{
				foreach($this->ancestry($classname)  as $class)
				{
					if (array_key_exists($property_name,$this->classes[strtolower($class)]->static))
					{
						$key=$property_name;	
						return $this->classes[strtolower($class)]->static; //only access its value #TODO: check for visibility
					}
				}
				$this->error("Access to undeclared static property: {$classname}::\${$property_name}");
			}
			else
				$this->error("Class '{$classname}' not found");
			return $this->null_reference($key);
		}
		elseif ($node instanceof Node\Expr\Variable and is_string($node->name) and $node->name=="this") //$this
		{
			$key='this';
			$this->this_hack=array($key=>&$this->this);

			return $this->this_hack;
		}
		else
			return parent::symbol_table($node,$key,$create);
	}
	public function call_function($name,$args)
	{
		if (is_array($name) and count($name)==2) //method call
		{
			$object=$name[0];
			$method_name=$name[1];
			$this->run_method($object,$method_name,$args);
		}
		elseif (is_string($name) and strpos($name,"::")!==false) //static call
		{
			list($class,$method)=explode("::",$name);
			$this->run_static_method($class,$method,$args);
		}
		else
			return parent::call_function($name,$args); //non-OO
	}

}

$x=new OOEmulator;
// $x->start("wordpress/index.php");
$x->start("wordpress/wp-admin/install.php");
// $x->start("sample-oo.php");
// echo "Output of size ".strlen($x->output)." was generated:",PHP_EOL;
// var_dump(substr($x->output,-200));
// echo(($x->output));