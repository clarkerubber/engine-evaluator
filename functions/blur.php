<?php

function BLpoints ( $games, $username, $threshold = NULL ) {
	//Input: A list of games with blurs
	//Output: Amount of points for blur rate

	global $SAMPLE_SIZE;
	global $POINTS_TOTAL, $BL_CONST_MIN_MOVES;

	if ( $threshold == NULL ){
		$threshold = $BL_CONST_MIN_MOVES;
	}

	$gamesWithData = 0;
	$unscaledPoints = 0;

	//For all of the games
	foreach( $games as $game ) {

		//Determine if the game has the required data
		if( isset( $game['players']['white']['userId'] ) && isset( $game['players']['black']['userId'] ) 
			&& isset( $game['players']['white']['blurs'] ) && isset( $game['players']['black']['blurs'] ) ) {

			//-----Determine What side the player is-----
			if( $game['players']['white']['userId'] == $username ) {
				//player is white
				$blurs = $game['players']['white']['blurs'];
				$moveCount = count( $game['players']['white']['moveTimes'] );
			} else {
				//player is black
				$blurs = $game['players']['black']['blurs'];
				$moveCount = count( $game['players']['black']['moveTimes'] );
			}

			if( $moveCount > $threshold ){
				$gamesWithData++;
				$unscaledPoints += BLpointsForGame( $blurs, $moveCount );
			}
		}
	}
	return scalePoints( $POINTS_TOTAL['BL'], $gamesWithData, $unscaledPoints );
}

function BLpointsForGame( $blurs, $moveCount ) {
	//Inputs: amount of blurs, amount of moves
	//Output: number between 0 -> 1 for suspiciousness

	//It's squared to make the points drop off faster for lesser blurs.
	$output = pow( $blurs / $moveCount, 2 );

	//Check if the output is within bounds 0 -> 1
	$output = ( $output > 1 ) ? 1 : ( ( $output < 0 ) ? 0 : $output );
	return $output;
}