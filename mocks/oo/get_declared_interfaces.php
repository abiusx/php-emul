<?php
function get_declared_interfaces_mock($emul)
{
	$out=[];
	foreach ($emul->classes as $k=>$class)
		if ($class->type=="interface")
			if (strtolower($k)==strtolower($class->name))
				$out[]=$class->name;
			else //alias
				$out[]=$k;
	return array_merge(get_declared_classes(),$out);
}