<?php
//TODO: handle arguments
//FIXME: output also buffers as long as buffering is on. test properly.

function ob_start_mock($emul, $callback=null,$chunk_size=0,$flags=null)
{
	array_unshift($emul->output_buffer,"");
	return true;
}
function ob_get_level_mock($emul)
{
	return count($emul->output_buffer);
}
function ob_end_clean_mock($emul)
{
	if (count($emul->output_buffer)<1) return false;
	array_shift($emul->output_buffer);
	return true;
}


function ob_get_flush_mock($emul)
{
	if (!ob_get_level_mock($emul)) return false;
	$r=$emul->output_buffer[0];
	ob_end_clean_mock($emul);
	$emul->output($r);
	return $r;
}
function ob_end_flush_mock($emul)
{
	if (!ob_get_level_mock($emul)) return false;
	ob_get_flush_mock($emul);
	return true;
}

function ob_clean_mock($emul)
{
	if (ob_get_level_mock($emul))
		$emul->output_buffer[0]="";
}
function ob_flush_mock($emul)
{
	if (ob_get_level_mock($emul))
	$r=ob_get_clean_mock($emul);
	$emul->output($r);
	ob_start_mock($emul);
	return $r;
}

function ob_get_clean_mock($emul)
{
	if (!ob_get_level_mock($emul)) return false;
	$r=$emul->output_buffer[0];
	ob_end_clean_mock($emul);
	return $r;
}
function ob_get_contents_mock($emul)
{
	if (!ob_get_level_mock($emul)) return false;
	$r=$emul->output_buffer[0];
	return $r;
}
function ob_get_length_mock($emul)
{
	if (!ob_get_level_mock($emul)) return false;
	return strlen($emul->output_buffer[0]);
}
function ob_get_status_mock($emul,$full_status=false)
{
	$status= array(
	    ['level'] => ob_get_level_mock($emul),
	    ['type'] => 1,
	    ['status'] => 0,
	    ['name'] => "default output handler",
	    ['del'] => 1
	);
	if ($full_status)
	{
		$res=[];
		unset($status['level']);
		for ($i=0;$i<count($emul->output_buffer);++$i)
		{
			$status['size']=strlen($emul->output_buffer[$i]);
			$status['blocksize']=10240;
			$status['chunksize']=0;
			$res[$i]=$status;
		}
		return $res;
	}
	else
		return $status;
}
//string ob_gzhandler ( string $buffer , int $mode )
function ob_implicit_flush_mock($emul,$flag=true)
{
	//TODO:
}
function ob_list_handlers_mock($emul)
{
	return array_fill(0,count($emul->output_buffer), array("default output handler"));
}
