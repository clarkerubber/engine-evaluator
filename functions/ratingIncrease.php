<?php

function RIpoints ( $progress ) {
	//Input: Rating increase over past 10 games
	//Output: Points for rating increase
	global $RI_POINTS_TOTAL, $RI_CONST_MIN, $RI_CONST_MAX;

	$output = 0;

	if( $progress > $RI_CONST_MIN ) {
		/*
					|    Progress - Minimum Progress      |
		output = 	|-------------------------------------|
					| Maximum Progress - Minimum Progress |
		*/
		$output = $RI_POINTS_TOTAL * ( $progress - $RI_CONST_MIN ) / ( $RI_CONST_MAX - $RI_CONST_MIN );
		if( $output > $RI_POINTS_TOTAL ){
			$output = $RI_POINTS_TOTAL;
		}
	}

	return $output;
}