<?php
require_once __DIR__."/emulator.php";
class EmulatorState extends Emulator
{

	function __construct()
	{
	}
	function extract_state($emul)
	{
		$res=[];
		$res['variables']		=	$emul->variables;

		$currents=['node','file','line','function','statement_index'];
		foreach ($currents as $current)
			$res["current_{$current}"]	=	$emul->{"current_{$current}"};
		
		$res['included_files']	=	$emul->included_files;
		$res['output']			=	$emul->output;
		$res['super_globals']	=	$emul->super_globals;
		$res['functions']		=	$emul->functions;
		$res['constants']		=	$emul->constants;
		$res['eval_depth']		=	$emul->eval_depth;
		$res['variable_stack']	=	$emul->variable_stack;
		$res['terminated']		=	$emul->terminated;
		$res['mock_functions']	= 	$emul->mock_functions;
		$res['trace']			=	$emul->trace;
		$res['break']			=	$emul->break;
		$res['continue']		=	$emul->continue;
		$res['loop_depth']		=	$emul->loop_depth;
		$res['return']			=	$emul->return;
		$res['return_value']	=	$emul->return_value;
		$res['shutdown_functions']	=	$emul->shutdown_functions;
		$res['silenced']		=	$emul->silenced;
		$res['entry_file']		=	$emul->entry_file;


		return $res;
	}
	function save($emul)
	{
		$newfile=$this->filename($emul);
		file_put_contents($newfile, gzcompress(serialize($this->extract_state($emul)),9) );
		return $newfile;
	}
	function filename($emul)
	{
		$entry_file=$emul->entry_file;
		$files=glob("{$entry_file}.state*.emu");
		sort($files);
		$last=end($files);
		if (!$last)
			return "{$entry_file}.state00000.emu";
		$index=substr(basename($last),-9,5)*1;
		return sprintf("{$entry_file}.state%05d.emu",$index+1);
	}
	function restore($file,$emul)
	{
		$state=unserialize(gzuncompress(file_get_contents($file)));
		foreach ($state as $key=>$value)
			$emul->$key=$value;
		return $state;
	}
}
