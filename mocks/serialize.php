<?php
function serialize_mock($emul,$value)
{
	// if ($value instanceof EmulatorObject) var_dump($value);
	if (is_array($value))
	{
		$out="a:".count($value).":{";
		foreach ($value as $k=>$v)
			$out.=serialize_mock($emul,$k).serialize_mock($emul,$v);
		$out.="}";
	}
	elseif (is_object($value) and $value instanceof EmulatorObject)
	{
		$out="O:".strlen($value->classname).':"'.$value->classname.'":'.count($value->properties).":";
		$t=[];
		foreach ($value->properties as $k=>$v)
		{
			if (!isset($value->property_visibilities[$k]) or $value->property_visibilities[$k]==EmulatorObject::Visibility_Public)
				$t[$k]=$v;
			elseif ($value->property_visibilities[$k]==EmulatorObject::Visibility_Protected)
				$t[chr(0).'*'.chr(0).$k]=$v;
			elseif ($value->property_visibilities[$k]==EmulatorObject::Visibility_Private)
				if (!isset($value->property_class[$k]))
					$t[chr(0).$value->classname.chr(0).$k]=$v; 
				else
					$t[chr(0).$value->property_class[$k].chr(0).$k]=$v; 

				#FIXME: should be for the class that defined this, but only the last one is accesible anyway
			else
				$emul->error("Shouldn't be here.");
		}
		$t=serialize_mock($emul,$t);
		$out.=substr($t,strpos($t,"{"));
	}
	else
		$out=serialize($value);

	return $out;
}