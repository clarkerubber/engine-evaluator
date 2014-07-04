<?php

function HApoints ( $games, $username ) {
	global $POINTS_TOTAL;

	$gamesWithData = 0;
	$unscaledPoints = 0;

	//For all of the games
	foreach( $games as $game ) {

		//Determine if the game has the required data
		if( isset( $game['players']['white']['userId'] ) && isset( $game['players']['black']['userId'] ) ) {

			//-----Determine What side the player is-----
			if( $game['players']['white']['userId'] == $username && isset( $game['players']['white']['hold'] ) ) {
				//player is white
				$holds = $game['players']['white']['hold'];
			} else if ( isset( $game['players']['black']['hold'] ) ) {
				//player is black
				$holds = $game['players']['black']['hold'];
			}

			if( isset( $holds ) ){
				$gamesWithData++;
				$unscaledPoints += HApointsForGame( $holds );
			}
			unset( $holds );
		}
	}
	return scalePoints( $POINTS_TOTAL['HA'], $gamesWithData, $unscaledPoints );
}

function HApointsForGame ( $holds ) {
	//Input: hold data from game
	//Output: number from 0->1 for how sus the game is

	GLOBAL $HA_MIN, $HA_MAX, $HA_PEAK, $HA_DECAY;

	return ( $HA_MAX - $HA_MIN ) * exp( - $HA_DECAY * pow( $holds['ply'] - $HA_PEAK , 2 ) ) + $HA_MIN;
}