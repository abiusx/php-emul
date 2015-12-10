<?php
require_once __DIR__."/emulator.php";
class EmulatorState extends EmulatorBase
{

	function __construct(Emulator $emul)
	{
		$this->emul=$emul;
	}
	function extract_state()
	{
		$res=[];
		$res['variables']		=	$emul->variables;

		$currents=['node','file','line','function'];
		foreach ($currents as $current)
			$res['current_{$current}']	=	$emul->{"current_{$current}"};
		
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
	function save()
	{
		$newfile=$this->filename();
		file_put_contents($newfile, serialize($this->extract_state()));
	}
	function filename()
	{
		$files=glob(__DIR__."/state/*");
		sort($files);
		$last=end($files);
		if (!$last)
		{
			@mkdir(__DIR__."/state");	
			return __DIR__."/state/state00000.emu";
		}
		$index=substr(basename($last),strlen("state"),5)*1;
		return sprintf(__DIR__."/state/state%05d.emu",$index+1);
	}
	function restore()
	{

	}
}
