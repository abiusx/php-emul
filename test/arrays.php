<?php
$a = array( 
			array(
					array(
							1, 2)
				)
			);
assert($a[0][0][1] == 2);

$arr = [];
$arr[] = 5;
$arr['mamad'] = 7;
$arr[$arr['mamad']] = strrev('CMU');
$arr[strrev('TIM')] = 4;
assert( $arr[0] == 5 &&
		$arr['mamad'] == 7 &&
		$arr[7] == 'UMC' &&
		$arr['MIT'] == 4
	);
	
