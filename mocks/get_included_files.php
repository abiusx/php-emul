<?php
function get_included_files_mock($emul)
{
	return array_keys($emul->included_files);
}

function get_required_files_mock($emul)
{
	return get_included_files_mock($emul);	
}