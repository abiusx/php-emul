<?php
function unserialize_mock_helper($x)
{
	if (is_array($x))
	{
		$r=[];
		foreach ($x as $k=>$v)
			$r[$k]=unserialize_mock_helper($v);
		return $r;
	}
	elseif (!is_object($x) and gettype($x)=="object" and get_class($x)==="__PHP_Incomplete_Class")
	{
		$props=$visibilities=$classes=[];

		foreach ((array)$x as $k=>$v)
		{
			if ($k=="__PHP_Incomplete_Class_Name")
			{
				$classname=$v;
				continue;
			}
			$key=$k;
			$value=$v;
			$visibility=EmulatorObject::Visibility_Public;
			if (strpos($k,chr(0))!==false)
			{
				if (substr($k,0,3)==chr(0)."*".chr(0))
				{
					$visibility=EmulatorObject::Visibility_Protected;
					$key=substr($k,3);
					$class=null;
				}
				else
				{
					$visibility=EmulatorObject::Visibility_Private;
					$key=substr($k,strpos($k,chr(0),1));
					$class=substr($k,1,strpos($k,chr(0),1)-1); //null+class+null+key
				}

			}
			else
				$class=$classname;
			$props[$key]=$value;
			$visibilities[$key]=$visibility;
			$classes[$key]=$class;
		}
		$o=new EmulatorObject($classname,$props,$visibilities,$classes);
		return $o;
	}
	else
		return $x;
}
function unserialize_mock($emul,$value)
{
	$r=unserialize($value);

	return unserialize_mock_helper($r);

}