<?php

function CApoints ( $games, $username, $threshold = NULL ) {
	//Input: A list of games with blurs
	//Output: Amount of points for blur rate

	global $SAMPLE_SIZE;
	global $POINTS_TOTAL, $CA_CONST_MIN_MOVES;

	if ( $threshold == NULL ) {
		$threshold = $CA_CONST_MIN_MOVES;
	}

	$gamesWithData = 0;
	$unscaledPoints = 0;

	//For all of the games
	foreach( $games as $game ) {

		//Determine if the game has the required data
		if( isset( $game['players']['white']['userId'] ) && isset( $game['players']['black']['userId'] ) 
			&& isset( $game['players']['white']['analysis'] ) && isset( $game['players']['black']['analysis'] ) ) {

			//-----Determine What side the player is-----
			if( $game['players']['white']['userId'] == $username ) {
				//player is white
				$analysis = $game['players']['white']['analysis'];
				$moveCount = count( $game['players']['white']['moveTimes'] );
			} else {
				//player is black
				$analysis = $game['players']['black']['analysis'];
				$moveCount = count( $game['players']['black']['moveTimes'] );
			}

			if( $moveCount > $threshold ){
				$gamesWithData++;
				$unscaledPoints += CApointsForGame( $analysis, $moveCount );
			}
		}
	}
	return scalePoints( $POINTS_TOTAL['CA'], $gamesWithData, $unscaledPoints );
}

function CApointsForGame ( $analysis, $moveCount ) {
	//Input: analysis and move count
	//Output: number from 0->1 for how sus the game is

	global $CA_CONST_INACCURACY, $CA_CONST_MISTAKE, $CA_CONST_BLUNDER;

	$inaccuracyRate = $analysis['inaccuracy'] / $moveCount;
	$mistakeRate = $analysis['mistake'] / $moveCount;
	$blunderRate = $analysis['blunder'] / $moveCount;

	$output = 1 - abs( $CA_CONST_INACCURACY * $inaccuracyRate + $CA_CONST_MISTAKE * $mistakeRate + $CA_CONST_BLUNDER * $blunderRate );

	//Again, square to cause people with high inaccuracy rates to drop off faster
	return ( $output < 0 ) ? 0 : pow( $output, 2 );
}