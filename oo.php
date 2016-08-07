<?php
require_once __DIR__."/emulator.php";
use PhpParser\Node;
#TODO: closure,closureUse
#TODO: namespace,use
//TODO: magic methods, destructor
//TODO: enforce visibilities
//TODO: print_r,var_export and var_dump output EmulatorObject not actual object

/**
 * The user defined objects are wrapped in this class.
 */
class EmulatorObject
{
	const Visibility_Public=1;
	const Visibility_Protected=2;
	const Visibility_Private=4;

	public static $emul=null;
	public static $object_count=0;
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
	/**
	 * Parent if inherited from a core PHP class
	 * @var  object 
	 */
	public $parent=null;
	/**
	 * A numeric value which is distinct for every object
	 * @var integer
	 */
	public $objectid;
	public function __construct($classname,$properties=[],$visibilities=[],$classes=[])
	{
		$this->objectid=self::$object_count++;
		$this->classname=$classname;
		self::$emul->verbose("EmulatorObject('{$this->classname}') __construct() id={$this->objectid}\n",5);
		$this->properties=$properties;
		$this->property_visibilities=$visibilities;
		$this->property_class=$classes;
	}
	function __clone()
	{
		$this->objectid=self::$object_count++;
		self::$emul->verbose("EmulatorObject('{$this->classname}')) __clone() id={$this->objectid}\n",5);
		//TODO: call clone
	}
	public $destructor=null;
	function __destruct()
	{
		self::$emul->verbose("EmulatorObject('{$this->classname}') __destruct() id={$this->objectid}\n",5);
		self::$object_count--;
		if (self::$emul->method_exists($this, "__destruct"))
			self::$emul->run_method($this,"__destruct");
		// TODO: call the internal destructor from OOEmulator instead of here
	}

	function __toString()
	{
		return $this->classname;
	}

}

require_once "oo-methods.php";
require_once "oo-spl-autoload.php";
class OOEmulator extends Emulator
{
	use OOEmulatorMethods;
	use OOEmulator_spl_autoload;
	function __construct($init_environ=null)
	{

		$this->state['autoloaders']=
		$this->state['classes']=
		$this->state['current_method']=
		$this->state['current_trait']=
		$this->state['current_this']=
		$this->state['current_self']=
		$this->state['current_class']=
		1; //emulation state elements
		parent::__construct($init_environ);
		EmulatorObject::$emul=$this; //HACK for allowing destructor calls
	}
	/**
	 * Holds the class definitions
	 * @var array
	 */
	public $classes=[];
	protected $current_method,$current_trait;
	/**
	 * Holds $this and self object and class pointers, as well as late static binding
	 * @var null
	 */
	protected $current_this=null,$current_self=null,$current_class=null;

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
			$classname=$this->current_namespace($this->name($node->name));
			$type=strtolower(substr(explode("\\",get_class($node))[3],0,-1)); #intertface, class, trait
			// $class_index=strtolower($this->namespace($classname));
			$class_index=strtolower($classname);
			$this->classes[$class_index]=new stdClass;
			$class=&$this->classes[$class_index];

			$extends=null;
			if (isset($node->extends) and $node->extends)
			{
				$extends=$this->namespaced_name($node->extends);
				if (!$this->class_exists($extends))	
					$this->spl_autoload_call($extends);
				if (!$this->class_exists($extends))
					$this->error("Class '{$extends}' not found");
				if ($this->user_class_exists($extends))
					$extends=$this->classes[strtolower($extends)]->name;
			}
			$this->verbose("Extracting declaration of class '{$classname}'...\n",3);
				
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
			$class->context=new EmulatorExecutionContext(['class'=>$classname,'self'=>$classname,'file'=>$this->current_file
				,'namespace'=>$this->current_namespace,'active_namespaces'=>$this->current_active_namespaces]);
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
							// "file"=>$this->current_file,
							'type'=>$type,'statics'=>[]); 
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
			// $class->file=$this->current_file;
			$class->interfaces=$interfaces;
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
		$class_index=strtolower($classname);
		
		$obj=new EmulatorObject($this->classes[$class_index]->name,$this->classes[$class_index]->properties,$this->classes[$class_index]->property_visibilities);
		$constructor=null;
		
		$t=explode("\\",$classname);
		$old_style_constructor=end($t); //strip namespace
		
		if ($this->user_method_exists($classname,"__construct"))
			$constructor="__construct";
		elseif ($this->user_method_exists($classname,$old_style_constructor))
			$constructor=$old_style_constructor;
		
		foreach ($this->ancestry($class_index,true) as $class)
		{
			if ($this->user_class_exists($class))
			foreach ($this->classes[strtolower($class)]->properties as $property_name=>$property)
			{
				$obj->properties[$property_name]=$property;
				$obj->property_visibilities[$property_name]=$this->classes[strtolower($class)]->property_visibilities[$property_name];
				$obj->property_class[$property_name]=$this->classes[strtolower($class)]->name;
			}
			else
			{
				if ($constructor) //has a constructor, do not auto-construct
				{
					$r=new ReflectionClass($class);
					$obj->parent=$r->newInstanceWithoutConstructor();
				}
				else
					$obj->parent=new $class;
			}
		}
		if ($constructor)
			$this->run_user_method($obj,$constructor,$args);
		// $this->verbose("Creation done!".PHP_EOL,2); ///DEBUG
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
		$this->verbose("New instance of core class '{$classname}'\n",5);
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
		$classname=$this->real_class($classname); //apparently 'new self' is ok!
		if (!$this->class_exists($classname))	
			$this->spl_autoload_call($classname);
		if ($this->user_class_exists($classname)) //user classes
			return $this->new_user_object($classname,$args);
		elseif (class_exists($classname)) //core classes
			return $this->new_core_object($classname,$args);

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

			$classname=$this->namespaced_name($node->class);
			return $this->new_object($classname,$node->args); //user function

		}
		elseif ($node instanceof Node\Expr\MethodCall)
		{
			$object=&$this->variable_reference($node->var);
			$method_name=$this->name($node->name);
			if ($object instanceof EmulatorObject)
				$classname=$object->classname;
			elseif (is_object($object))
				$classname=get_class($object);
			else
				$this->error("Call to a member function '{$method_name()}' on a non-object");
			$this->verbose("Method call {$classname}::{$method_name}()".PHP_EOL,3);
			$args=$node->args;
			return $this->run_method($object,$method_name,$args);
		}
		elseif ($node instanceof Node\Expr\StaticCall)
		{
			#TODO: namespace support
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
			if (!is_object($var)) return $this->error("_clone method called on non-object",$node);
			$var2=clone $var;
			if ($this->method_exists($var2, "__clone"))
				$this->run_method($var2,"__clone");
			// $var2->properties=[];
			// foreach ($var->properties as $k=>$property)
			// 	$var2->properties[$k]=clone $property;
			return $var2;
		}
		elseif ($node instanceof Node\Expr\Instanceof_)
		{
			$var=$this->evaluate_expression($node->expr);
			if (!is_object($var)) return false;
			//here needs FQ because classname is string, not going through ancestry
			$classname=$this->namespaced_name($node->class);
			return $this->is_a($var,$classname);
			// if ($var instanceof EmulatorObject)
			// {
			// 	foreach ($this->ancestry($var->classname) as $class)
			// 		if (strtolower($class)===strtolower($classname)) return true;
			// 	return false;
			// }
			// else
			// 	return $var instanceof $classname;
		}		
		elseif ($node instanceof Node\Expr\Cast\Object_)
				return $this->to_object($this->evaluate_expression($node->expr));
		elseif ($node instanceof Node\Expr\Cast)
		{
			$expr=$this->evaluate_expression($node->expr);
			if ($expr instanceof EmulatorObject) 
			{
				if ($node instanceof Node\Expr\Cast\Array_)
					return (array)$expr->properties; #FIXME: php does this like serialize, e.g private is nullNAMEnull=>val
				#TODO: what if scalar object is cast back to scalar?
				#TODO: string cast (call magic __toString)
			}
			elseif ($node instanceof Node\Expr\Cast\Object_)
				return $this->to_object($expr);
		}

		return parent::evaluate_expression($node);

	}	
	/**
	 * Converts any value to an emulator object
	 * @param  mixed $val 
	 * @return EmulatorObject      
	 */
	protected function to_object($val)
	{
		return (object)$val; //convert to direct stdClass instead of Emulator object
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
		while (isset($this->classes[strtolower($classname)]) 
			and $this->classes[strtolower($classname)]->parent)
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
		// elseif ($node instanceof Node\Stmt\Unset_) //magic method
		// {
		// 	#TODO:
		// }
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
			$classname=$this->real_class($classname);
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
			$key='temp';
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

	/**
	 * Handles magic method
	 * @param  [type] $node  [description]
	 * @param  [type] $magic [description]
	 * @param  array  $args  [description]
	 * @return [type]        [description]
	 */
	private function handle_magic($node,$magic,&$result,$args=[])
	{
		if ($node instanceof Node\Expr\PropertyFetch)// and !parent::variable_isset($node))
		{

			$base=&$this->symbol_table($node->var,$key2,false);
			if ($key2===null)
				return false;
			$obj=&$base[$key2];
			if ($obj instanceof EmulatorObject)	
				$prop=$this->name($node->name);
			else
				return false;
			if (array_key_exists($prop, $obj->properties)) return false; //exists
			
			foreach ($this->ancestry($obj->classname) as $class)
				if ($this->user_method_exists($class,"__{$magic}")) //magic_method
				{
					$this->verbose("Calling magic method {$class}::__{$magic}() for '{$prop}'...\n",3);
					array_unshift($args,$prop);
					$result=$this->run_user_method($obj,"__{$magic}",$args,$class);
					return true;
				}
		}	
		return false;
	}

	function variable_set($node,$value=null)
	{
		if ($this->handle_magic($node,"set",$r,[$value])) return $r;
		else return parent::variable_set($node,$value);
		// $r=&$this->symbol_table($node,$key,true);
		// if ($key!==null)
		// 	return $r[$key]=$value;
		// else 
		// 	return null;
	}
	function &variable_reference($node)
	{
		if ($this->handle_magic($node,"get",$r)) return $r;
		else return parent::variable_reference($node);

		// $r=&$this->symbol_table($node,$key,false);
		// if ($key===null) //not found or GLOBALS
		// 	return $this->null_reference();
		// elseif (is_array($r))
		// 	return $r[$key]; //if $r[$key] does not exist, will be created in byref use.
		// else
		// 	$this->error("Could not retrieve reference",$node);
	}
	function variable_get($node)
	{
		if ($this->handle_magic($node,"get",$r)) return $r;
		else return parent::variable_get($node);
		// $r=&$this->symbol_table($node,$key,false);
		// if ($key!==null)
		// 	if (is_string($r))
		// 		return $r[$key];
		// 	elseif (!array_key_exists($key, $r)) //only works for arrays, not strings
		// 	{
		// 		$this->notice("Undefined index: {$key}");
		// 		return null;
		// 	}
		// 	else
		// 		return $r[$key];
		// else 
		// 	return null;
	}
	function variable_isset($node)
	{
		if ($this->handle_magic($node,"isset",$r)) return $r;
		else return parent::variable_isset($node);	
	
		// $this->error_silence();
		// $r=$this->symbol_table($node,$key,false);
		// $this->error_restore();
		// return $key!==null and isset($r[$key]);
	}
	function variable_unset($node)
	{
		if ($this->handle_magic($node,"unset",$r)) return $r;
		else return parent::variable_unset($node);	
	}



// #NOTE: __set overrides $create, even if create is true, __set is called first
// 						#TODO: ancestry
// 						#TODO: these should be moved to variable_set, etc.
// 						if ($this->user_method_exists($var,"__get")) //magic_method
// 						{
// 							$this->verbose("Calling magic method __get() for '{$property_name}'...\n",3);
// 							$temp=['temp'=>$this->run_user_method($var,"__get",[$property_name])];
// 							$key='temp';
// 							return $temp;
// 						}
}

foreach (glob(__DIR__."/mocks/oo/*.php") as $mock)
	require_once $mock;
unset($mock);
