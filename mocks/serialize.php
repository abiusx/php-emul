<?php
// function _zval_id_accurate(&$zval,&$zvals)
// {
// 	if ($zvals===null) $zvals=[];
// 	$backup=$zval;
// 	$zval="___VISITED___";
// 	foreach ($zvals as $k=>$v)
// 		if ($v==='___VISITED___')
// 		{
// 			$id=$k;
// 			break;
// 		}
// 	$zval=$backup;
// 	if (!isset($id))
// 	{
// 		$id=count($zvals);
// 		$zvals[$id]=&$zval;
// 	}
// 	return $id;
// }

function serialize_mock_helper(&$value,&$done=[],&$zvals=[])
{
	#TODO: handle recursion 
	// $id=zval_id($value);
	// $id=_zval_id_accurate($value,$zvals);
	// var_dump($value);
	// echo "ID: {$id}\n";
	// if (isset($done[$id]))
	// 	return "R:".$done[$id].";";
	// $done[$id]=count($done);
	// if ($value instanceof EmulatorObject) var_dump($value);
	if (is_array($value))
	{
		$out="a:".count($value).":{";
		foreach ($value as $k=>&$v)
			$out.=serialize_mock_helper($k,$done,$zvals).serialize_mock_helper($v,$done,$zvals);
		$out.="}";
	}
	elseif (is_object($value) and $value instanceof EmulatorObject)
	{
		$out="O:".strlen($value->classname).':"'.$value->classname.'":'.count($value->properties).":";
		$t=[];
		foreach ($value->properties as $k=>&$v)
		{
			if (!isset($value->property_visibilities[$k]) or $value->property_visibilities[$k]==EmulatorObject::Visibility_Public)
				$t[$k]=$v;
			elseif ($value->property_visibilities[$k]==EmulatorObject::Visibility_Protected)
				$t[chr(0).'*'.chr(0).$k]=&$v;
			elseif ($value->property_visibilities[$k]==EmulatorObject::Visibility_Private)
				if (!isset($value->property_class[$k]))
					$t[chr(0).$value->classname.chr(0).$k]=&$v; 
				else
					$t[chr(0).$value->property_class[$k].chr(0).$k]=&$v; 

				#FIXME: should be for the class that defined this, but only the last one is accesible anyway
			else
				throw new Exception("Shouldn't be here.");
		}
		$t=serialize_mock_helper($t,$done,$zvals);

		$out.=substr($t,strpos($t,"{"));
	}
	else
		$out=serialize($value);
	return $out;
}
function serialize_mock($emul,$value)
{
	return serialize_mock_helper($value);
}
