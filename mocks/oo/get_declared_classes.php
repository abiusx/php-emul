<?php
function get_declared_classes_mock($emul)
{
	return array_merge(get_declared_classes(),array_values(array_map(function ($x) { return $x->name;}, $emul->classes)));
}