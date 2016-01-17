<?php
function get_defined_functions_mock($emul)
{
	return ["internal"=>get_defined_functions()['internal'],'user'=>array_keys($emul->functions)];
}