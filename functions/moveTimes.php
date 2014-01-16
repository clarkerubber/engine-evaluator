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
					| Threshold - Deviation Over Mean |2
		output = 	|---------------------------------|
					| Threshold - Adjustment Variable |

		Basically takes the diviation over mean and returns a scaled value between 0 -> 1

		*/
		$output = pow( ( $SD_CONST_TRESHOLD - $deviation ) / ( $SD_CONST_TRESHOLD - $SD_CONST_ADJ ) , 2 );
	}

	return ( $output > 1 ) ? 1 : $output;
}

function SDdeviation ( $moves, $remove_outliers = NULL ) {
	//Input: A list of moves
	//Output: Standard Deviation / Mean Move Time
	global $SD_PRE_MOVE_CORRECTION, $SD_REMOVE_OUTLIERS, $SD_PRE_MOVE_TIME;
	global $SD_OUTLIER_PRECENTAGE;

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
	

	$sum = 0;
	$squareDif = 0;
	$moveCount = count( $moves ); 

	//Determine Mean of Times
	foreach( $moves as $time ){
		$sum += $time;
	}
	$mean = $sum / $moveCount;

	//Find population distribution
	foreach( $moves as $time ){
		$squareDif += pow( $mean - $time, 2 );
	}
	$deviation = sqrt( $squareDif / $moveCount );

	return $deviation;
}