<?php

function functionalURL ( $address ){
	$headers = get_headers($address);

	$output = FALSE;

	if( intval( substr($headers[0], 9, 3) ) < 400) {
		$output = TRUE;
	}
    return $output;
}

function scalePoints ( $maximumOutput, $gamesWithData, $unscaledPoints ) {
	//Input: Unscaled, un-weighted points
	//Output: Scaled and weighted points

	/*Purpose of this function:
	A player can/will only have a limited amount
	of games with the necessary information that is being
	searched for. This function scales the given information
	over the target sample size.
	*/
	if($gamesWithData > 0){
		$output = $maximumOutput * $unscaledPoints / $gamesWithData;

		if( $output > $maximumOutput ){
			$output = $maximumOutput;
		} else if( $output < 0 ){
			$output = 0;
		}
	} else {
		$output = 0;
	}

	return $output;
}