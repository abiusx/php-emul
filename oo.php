<?php
require_once __DIR__."/emulator.php";
use PhpParser\Node;
#TODO: closure,closureUse
#TODO: namespace,use
//TODO: magic methods, destructor
//TODO: enforce visibilities
//TODO: print_r,var_export and var_dump output EmulatorObject not actual object
require_once "oo-methods.php";

trait OOEmulator_spl_autoload 
{
	protected $autoloaders=[];
	public function spl_autoload_register($callback=null, $throw=true, $prepend=false)
	{
		if ($callback===null)
			$callback="spl_autoload"; //default autoloader of php
		if ($prepend)
			array_unshift($this->autoloaders, $prepend);
		else
			$this->autoloaders[]=$callback;
		return true;
	}
	public function spl_autoload_unregister($callback)
	{
		if ( ($key=array_search($callback, $this->autoloaders))!==false)
		{
			unset($this->autoloaders[$key]);
			return true;
		}
		return false;

	}
	public function spl_autoload_functions()
	{
		return $this->autoloaders;
	}
	public function spl_autoload_call($class)
	{
		foreach ($this->autoloaders as $autoloader)
			if ($this->class_exists($class)) break;
			else $this->call_function($autoloader,[$class]);
	}
	protected $autoload_extensions=".inc,.php";
	public function spl_autoload_extensions($extensions=null)
	{
		if ($extensions===null) return $this->autoload_extensions;
		spl_autoload_extensions($extensions);
		$this->autoload_extensions=$extensions;
	}
	function autoload($class)
	{
		return $this->spl_autoload_call($class);
	}
}

/**
 * The user defined objects are wrapped in this class.
 */
class EmulatorObject
{
	const Visibility_Public=1;
	const Visibility_Protected=2;
	const Visibility_Private=4;
	/**
	 * @var string
	 */
	public $classname;
	/**
	 * Array of keys as propname, values as EmulatorObjectProperty
	 * @var [type]
	 */
	public $properties;
	/**
	 * Visibility of properties. 
	 * if a visibility does not exist for a property (dynamic creation), it's assumed public
	 * @var [type]
	 */
	public $property_visibilities;
	/**
	 * Which class this property was generated from (inheritance)
	 * @var array
	 */
	public $property_class=[];

	public function __construct($classname,$properties=[],$visibilities=[],$classes=[])
	{
		$this->classname=$classname;
		$this->properties=$properties;
		$this->property_visibilities=$visibilities;
		$this->property_class=$classes;
	}
	function __destruct()
	{
		#TODO: call the internal destructor
	}

	function __toString()
	{
		return $this->classname;
	}

}
class OOEmulator extends Emulator
{
	use OOEmulatorMethods;
	use OOEmulator_spl_autoload;
	function __construct()
	{
		parent::__construct();
	}
	/**
	 * Holds the class definitions
	 * @var array
	 */
	public $classes=[];
	protected $current_method,$current_trait;
	protected $current_namespace;
	/**
	 * Holds $this and self object and class pointers, as well as late static binding
	 * @var null
	 */
	public $current_this=null,$current_self=null,$current_class=null;

	/**
	 * Extract ClassLike declarations from files.
	 * @param  [type] $node [description]
	 * @return [type]       [description]
	 */
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
			$type=strtolower(substr(explode("\\",get_class($node))[3],0,-1)); #intertface, class, trait
			$this->classes[strtolower($classname)]=new stdClass;
			$class=&$this->classes[strtolower($classname)];

			$extends=null;
			if (isset($node->extends) and $node->extends)
			{
				$extends=$this->name($node->extends);
				if (!$this->class_exists($extends))
					$this->error("Class '{$extends}' not found");
				$extends=$this->classes[strtolower($extends)]->name;
			}
				
			$class->name=$classname;
			$class->interfaces=[];
			$class->traits=[];
			$class->consts=[];
			$class->type=$type;
			$class->methods=[];
			$class->properties=[];
			$class->property_visibilities=[];
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
							$class->property_visibilities[$propname]=$visibility;
					}
				}
				elseif ($part instanceof Node\Stmt\ClassMethod)
				{
					$methodname=$this->name($part->name);
					$type=$part->type;
					$class->methods[strtolower($methodname)]=(object)array('name'=>$methodname,"params"=>$part->params,"code"=>$part->stmts,
							"file"=>$this->current_file,'type'=>$type,'statics'=>[]); 
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
				elseif ($part instanceof Node\Stmt\TraitUse)
				{
					foreach ($part->traits as $trait)
						$class->traits[]=$this->name($trait);
				}
				else
					$this->error("Unknown class part for class '{$classname}'",$part);

			}
			$interfaces=[];
			if (isset($node->implements))
			foreach ($node->implements as $interface)
				$interfaces[]=$this->name($interface);
			$class->classtype=$classtype;
			$class->file=$this->current_file;
			$class->interfaces=$interfaces;
			// $class=(object)["properties"=>$properties,"static"=>$static_properties,"consts"=>$consts,"methods"=>$methods,'parent'=>$extends,'interfaces'=>$interfaces,'type'=>$classtype,'file'=>$this->current_file];
			// $this->classes[$classname]=$class;
		}
		else
			parent::get_declarations($node);

	}
	/**
	 * Create an object from a user defined class
	 * @param  string $classname 
	 * @param  array  $args  		constructor args   
	 * @return EmulatorObject            
	 */
	protected function new_user_object($classname,array $args)
	{
		$this->verbose("Creating object of type {$classname}...".PHP_EOL,2);
		$obj=new EmulatorObject($this->classes[strtolower($classname)]->name,$this->classes[strtolower($classname)]->properties,$this->classes[strtolower($classname)]->property_visibilities);
		foreach ($this->ancestry($classname,true) as $class)
		{
			foreach ($this->classes[strtolower($class)]->properties as $property_name=>$property)
			{
				$obj->properties[$property_name]=$property;
				$obj->property_visibilities[$property_name]=$this->classes[strtolower($class)]->property_visibilities[$property_name];
				$obj->property_class[$property_name]=$this->classes[strtolower($class)]->name;
			}
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
	/**
	 * Instantiate a core class into a native php object
	 * @param  string $classname 
	 * @param  array  $args      
	 * @return object            
	 */
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
	/**
	 * Instantiate a class into an object (native or user defined)
	 * @param  string $classname 
	 * @param  array  $args      
	 * @return object            
	 */
	protected function new_object($classname,array $args)
	{
		if (array_key_exists(strtolower($classname), $this->classes)) //user classes
			return $this->new_user_object($classname,$args);
		elseif (class_exists($classname)) //core classes
			return $this->new_core_object($classname,$args);
		else
			$this->error("Class '{$classname}' not found ");
	}
	/**
	 * Evaluate expressions specific to object orientation
	 * @param  Node $node 
	 * @return mixed       
	 */
	protected function evaluate_expression($node)
	{
		$this->expression_preprocess($node);		
		if ($node instanceof Node\Expr\New_)
		{

			$classname=$this->name($node->class);
			return $this->new_object($classname,$node->args); //user function

		}
		elseif ($node instanceof Node\Expr\MethodCall)
		{
			$object=&$this->variable_reference($node->var);
			$method_name=$this->name($node->name);
			$this->verbose("Method call ".$object->classname."::".$method_name.PHP_EOL,3);
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
					if (strtolower($class)===strtolower($classname)) return true;
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
	 * Converts any value to an emulator object
	 * @param  mixed $val 
	 * @return EmulatorObject      
	 */
	protected function to_object($val)
	{
		$obj=new EmulatorObject("stdClass");
		if (is_array($val))
		{
			foreach ($val as $k=>$v)
			{
				$obj->properties[$k]=$v;
				$obj->property_visibilities[$k]=EmulatorObject::Visibility_Public;
			}
		}
		else
		{
			$obj->properties['scalar']=$val;
			$obj->property_visibilities['scalar']=EmulatorObject::Visibility_Public;
		}

		return $obj;
	}
	/**
	 * Finds the real class name of a class reference (e.g self, parent, static, etc.)
	 * @param  string $classname 
	 * @return string            
	 */
	protected function real_class($classname)
	{
		if ($classname==="self")
			$classname=$this->current_self;
		elseif ($classname==="static")
			$classname=$this->current_class;
		elseif ($classname==="parent")
			$classname=$this->classes[strtolower($this->current_class)]->parent;	

		return $classname;
	}
	/**
	 * Returns all parents, including self, of a class, ordered from youngest
	 * Looks up self and static keywords
	 * @param  string $classname 
	 * @return array            
	 */
	public function ancestry($classname,$top_to_bottom=false)
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
	/**
	 * Run object oriented statements
	 * @param  Node $node 
	 * @return null       
	 */
	protected function run_statement($node)
	{
		if ($node instanceof Node\Stmt\ClassLike)
			return;
		elseif ($node instanceof Node\Stmt\Static_)
		{
			//TODO: bind this static variable to the method being runned in the class it belongs to
			$isMethod= (end($this->trace)->type=="::" or end($this->trace)->type=="->");
			if ($isMethod and  $this->user_method_exists(end($this->trace)->class,end($this->trace)->function)) //statc inside a method)
			{
				$class=end($this->trace)->class;
				$method=end($this->trace)->function;

				//TODO
				$statics=&$this->classes[strtolower($class)]->methods[strtolower($method)]->statics;// &$this->functions[$this->current_function]->statics;
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

	/**
	 * Symbol table lookup specific to object orientation
	 * See Emulator::symbol_table for more
	 * @param  Node  $node   
	 * @param  string  &$key   if null lookup failed
	 * @param  boolean $create create variable if not exists
	 * @return array base array          
	 */
	protected function &symbol_table($node,&$key,$create=true)
	{
		if ($node instanceof Node\Expr\PropertyFetch)
		{
			$base=&$this->symbol_table($node->var,$key2,$create);
			if ($key2===null)
			{
				$name=is_string($node->var)?$node->var:"Unknown";
				$this->notice("Undefined variable: {$name}");	
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
					else //dynamic properties, on all classes 
					{
						$var->properties[$property_name]=null;
					}
				}
				$key=$property_name;
				return $var->properties; //reference its value only!
			}
			elseif(is_object($var)) //native object
			{
				$property_name=$this->name($node->name);
				$this->verbose(sprintf("Fetching object property: %s::$%s\n",get_class($var),$property_name),4);
				// if (!isset($var->{$property_name}))
				// 	$this->notice("Undefined property: ".get_class($var)."::\${$property_name}");
				#TODO: review this
				$temp=['temp'=>&$var->{$property_name}];
				$key='temp';
				return $temp;
				// return $var->{$property_name}; //self notice? #TEST
			}
			else 
			{
				$this->notice("Trying to get property of non-object",$var);
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
			$t=array($key=>&$this->current_this);
			return $t;
		}
		else
			return parent::symbol_table($node,$key,$create);
	}
	/**
	 * Call a function. This does the same thing as call_user_func_array
	 * but for emulator. Can handle all 6 types of dynamic function call
	 * @param  mixed $name array of object/method or class/method, or string of static method or function name
	 * @param  array $args 
	 * @return mixed       function return value
	 */
	public function call_function($name,$args)
	{
		$this->stash_ob();

		#http://php.net/manual/en/language.types.callable.php
		if (is_array($name) and count($name)==2) //method call
		{
			$class_or_obj=$name[0];
			$method_name=$name[1];
			if (is_string($class_or_obj)) //class, type 2
			{
				#TODO: handle type 5 here	
				$ret=$this->run_static_method($class_or_obj,$method_name,$args);
			}
			elseif (is_object($class_or_obj)) //object, type 3
				$ret=$this->run_method($class_or_obj,$method_name,$args);
			else
				$this->error("Unknown function call '{$name}'.");
		}
		elseif (is_string($name) and strpos($name,"::")!==false) //static call
		{
			list($class,$method)=explode("::",$name); //type 4, static method call
			$ret=$this->run_static_method($class,$method,$args);
		}
		else
			#TODO: handle type 6 (invoke)
			$ret=parent::call_function($name,$args); //non-OO
		$this->restore_ob();
		return $ret;
	}

	/**
	 * Compatible with PHP's is_a
	 * @param  [type]  $object_or_string [description]
	 * @param  [type]  $class_name       [description]
	 * @param  boolean $allow_string     [description]
	 * @return boolean                   [description]
	 */
	public function is_a($object_or_string,$class_name,$allow_string=false)
	{
		if (is_object($object_or_string) and !($object_or_string instanceof EmulatorObject))
			return is_a($object_or_string,$class_name);
		if (is_string($object_or_string) and $allow_string!=true) return null;
		if (is_string($object_or_string) and !$this->user_class_exists($object_or_string))
			return is_a($object_or_string,$class_name,true);
		if (is_object($object_or_string))
			$class=$this->get_class($object_or_string);
		else
			$class=$object_or_string;
		
		foreach ($this->ancestry($class) as $ancestor)
			if (strtolower($ancestor)===strtolower($class_name))
				return true;	
		return false;
	}
	/**
	 * Compatible with PHP's is_subclass_of
	 * @param  [type]  $object_or_string [description]
	 * @param  [type]  $class_name       [description]
	 * @param  boolean $allow_string     [description]
	 * @return boolean                   [description]
	 */
	public function is_subclass_of($object_or_string, $class_name,$allow_string=true)
	{
		if (is_object($object_or_string) and !($object_or_string instanceof EmulatorObject))
			return is_subclass_of($object_or_string,$class_name);
		if (is_string($object_or_string) and $allow_string!=true) return null;
		if (is_string($object_or_string) and !$this->user_class_exists($object_or_string))
			return is_subclass_of($object_or_string,$class_name,true);
		if (is_object($object_or_string))
			$class=$this->get_class($object_or_string);
		else
			$class=$object_or_string;
		
		foreach ($this->ancestry($class) as $ancestor)
			if (strtolower($class)!=strtolower($ancestor) and strtolower($ancestor)===strtolower($class_name))
				return true;	
		return false;
	}

}

foreach (glob(__DIR__."/mocks/oo/*.php") as $mock)
	require_once $mock;
