<?php
function extract_mock($emul, &$array , $flags = EXTR_OVERWRITE , $prefix = NULL )
{
	#possible flags: EXTR_SKIP, EXTR_PREFIX_SAME,EXTR_PREFIX_ALL,EXTR_PREFIX_INVALID,EXTR_IF_EXISTS,EXTR_PREFIX_IF_EXISTS,EXTR_REFS
	$args=func_get_args();
	array_shift($args);
	$count=0;
	foreach ($array as $k=>$v)
	{
		$exists=isset($emul->variables[$k]);
		$key=$k;
		if ($flags&EXTR_OVERWRITE or ($flags&EXTR_IF_EXISTS and $exists))
			$key=$k;
		elseif (($exists and $flags&EXTR_PREFIX_SAME) or $flags&EXTR_PREFIX_ALL or ($exists and $flags&EXTR_PREFIX_IF_EXISTS ))
			$key=$prefix.$k;
		elseif ($flags&EXTR_PREFIX_INVALID and is_numeric($k))
			$key=$prefix.$k;
		elseif ($flags&EXTR_SKIP and $exists)
			$key=null;

		if ($key!==null)
		{
			$count++;	
			if ($flags & EXTR_REFS)
				$emul->variables[$key]=&$array[$k];
			else
				$emul->variables[$key]=$v;
		}

	}

	return $count;
}