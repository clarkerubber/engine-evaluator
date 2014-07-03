<?php

function SDpoints ( $games, $username, $threshold = NULL ) {
	//Inputs: List of games and target username
	//Output: Points - a score of how suspicious a player is.

	global $SAMPLE_SIZE;
	global $POINTS_TOTAL, $SD_CONST_MIN_MOVES;

	if ( $threshold == NULL ) {
		$threshold = $SD_CONST_MIN_MOVES;
	}

	$gamesWithData = 0;
	$unscaledPoints = 0;

	//For all of the games
	foreach( $games as $game ) {

		//Determine if the game has the required data
		if( isset( $game['players']['white']['userId'] ) && isset( $game['players']['black']['userId'] ) ) {

			//-----Determine What side the player is-----
			if( $game['players']['white']['userId'] == $username ) {
				//player is white
				$moves = $game['players']['white']['moveTimes'];
			} else {
				//player is black
				$moves = $game['players']['black']['moveTimes'];
			}

			//-----Add to unscaled points for move times
			if( count($moves) > $threshold ) {
				$gamesWithData++;
				$unscaledPoints += SDpointsForGame( $moves );
			}
		}
	}
	return scalePoints( $POINTS_TOTAL['SD'], $gamesWithData, $unscaledPoints );
}

function SDpointsForGame ( $moves ) {
	//Input: A list of moves
	//Output: A number between 0 -> 1 of how suspicious that game is

	global $SD_CONST_TRESHOLD, $SD_CONST_ADJ;

	$deviation = SDdeviation( $moves );
	$output = 0;

	if( $deviation < $SD_CONST_TRESHOLD ){
		/*
					|      Threshold - Deviation      |
		output = 	|---------------------------------|
					| Threshold - Adjustment Variable |

		Basically takes the diviation over mean and returns a scaled value between 0 -> 1

		*/
		$adjustment = SDrange( SDmean( $moves ) );
		$output = pow( ( $SD_CONST_TRESHOLD - $deviation ) / ( $SD_CONST_TRESHOLD - $adjustment ), 2 );
		//echo "$output = $SD_CONST_TRESHOLD - $deviation / $SD_CONST_TRESHOLD - $adjustment \n";
	}

	return ( $output > 1 ) ? 1 : $output;
}

function SDrange ( $mean ) {
	global $SD_AVERAGE_DIVISOR, $SD_RANGE_LOWER_LIMIT, $SD_RANGE_UPPER_LIMIT;

	if ( $mean < $SD_RANGE_LOWER_LIMIT ) {
		$range = $SD_RANGE_LOWER_LIMIT / $SD_AVERAGE_DIVISOR;
	} else if ( $mean > $SD_RANGE_UPPER_LIMIT ) {
		$range = $SD_RANGE_UPPER_LIMIT / $SD_AVERAGE_DIVISOR;
	} else {
		$range = $mean / $SD_AVERAGE_DIVISOR;
	}
	//echo "Mean: $mean => Range: $range\n";

	return $range;
}

function SDmean ( $moves ) {
	$sum = 0;
	foreach( $moves as $time ){
		$sum += $time;
	}
	return $sum / count( $moves );
}

function SDdeviation ( $moves, $remove_outliers = NULL ) {
	//Input: A list of moves
	//Output: Standard Deviation / Mean Move Time
	global $SD_PRE_MOVE_CORRECTION, $SD_REMOVE_OUTLIERS, $SD_PRE_MOVE_TIME;
	global $SD_OUTLIER_PERCENTAGE;

	if ( $remove_outliers == NULL ) {
		$remove_outliers = $SD_REMOVE_OUTLIERS;
	}

	arsort( $moves );

	foreach ($moves as $key => $value) {
		if ( $value < $SD_PRE_MOVE_TIME ) {
			$moves[$key] = $SD_PRE_MOVE_CORRECTION;
		}
	}


	if ( $remove_outliers == TRUE ) {
		for ( $x = 0; $x < floor ( $SD_OUTLIER_PERCENTAGE * count( $moves ) ); $x++ ) {
			array_shift( $moves );
		}	
	}
	
	$squareDif = 0;
	$moveCount = count( $moves );

	$mean = SDmean( $moves );

	//Find population distribution
	foreach( $moves as $time ){
		$squareDif += pow( $mean - $time, 2 );
	}
	$deviation = sqrt( $squareDif / $moveCount );

	return $deviation;
}