<?php

function SDpoints ( $games, $username ) {
	//Inputs: List of games and target username
	//Output: Points - a score of how suspicious a player is.

	global $SAMPLE_SIZE;
	global $POINTS_TOTAL, $SD_CONST_MIN_MOVES;

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
			if( count($moves) > $SD_CONST_MIN_MOVES ) {
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

	$deviationOverMean = SDdeviationOverMean( $moves );
	$output = 0;

	if( $deviationOverMean < $SD_CONST_TRESHOLD ){
		/*
					| Threshold - Deviation Over Mean |2
		output = 	|---------------------------------|
					| Threshold - Adjustment Variable |

		Basically takes the diviation over mean and returns a scaled value between 0 -> 1

		*/
		$output = pow( ( $SD_CONST_TRESHOLD - $deviationOverMean ) / ( $SD_CONST_TRESHOLD - $SD_CONST_ADJ ) , 2 );
	}

	return ( $output > 1 ) ? 1 : $output;
}

function SDdeviationOverMean ( $moves ) {
	//Input: A list of moves
	//Output: Standard Deviation / Mean Move Time

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

	return $deviation / $mean;
}