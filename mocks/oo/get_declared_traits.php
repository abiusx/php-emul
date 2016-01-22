<?php
function get_declared_traits_mock($emul)
{
	$out=[];
	foreach ($emul->classes as $k=>$class)
		if ($class->type=="trait")
			if (strtolower($k)==strtolower($class->name))
				$out[]=$class->name;
			else
				$out[]=$k;
	return array_merge(get_declared_classes(),$out);
}