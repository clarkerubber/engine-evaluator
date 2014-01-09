<?php

/*----------Synopsis----------
Aim: To provide a grading system for players with
regards to the likelihood that they are cheating.

Method: There are 5 main points of information that
can be used to aid in identifying cheaters, that are:
	-IP relationship to known cheaters
	-Rating progress
	-Blur rate
	-Perfection of moves (computer analysis)
	-Standard Distribution of move times

The relationship to known cheaters is boolean, so 
points are either allocated or not.

	IPpoints ~ IF IP relationship to known engine

The rating progress is dependant on the win rate,
RD, and opponent ratings. This system is to be improved.
Currently is is a linear relationship with rating increase.

	RIpoints ~ Increase in rating

Points assigned for blur rate is a square relationship
with the proportion of blurs to moves, where the 
proportion is 0 -> 1. This is so players with less
blurs fall off faster.

	BLpoints ~ blur rate ^ 2

Perfection of moves is harder to quantify. 0 inaccuracies,
0 mistakes, and 0 blunders will obviously be assigned the
maximum amounts of points, but at what rate will points
be diminished?

This approach shown here starts each game with 1 point
(the maximum possible), then subtracts the proportion
of each type of error (0 -> 1) from the starting value 
using different coefficients for each.

	CApoints ~ 1 - error rate

The relationship between move time and probability
of cheating is the most complicated process in this system.
By inspection, players who have more consistent move times
have a higher likelihood of cheating. The more consistent
a players move time, the lower the standard distribution (SD)
of move times.

However, for fast games, even a legitimate players SD
will be relatively low. And in long games, a cheaters SD
can be elevated. Using this knowledge, this system
uses the ratio of SD / Mean (average) to determine if a player
is likely cheating. This value is then 

	SDpoints ~ ( 1 - SD / Mean ) ^ 2

These points are then added together with different weights
to each to give an overall Cheat Index.


-----------------------------*/

include("keys.php"); //include keys to lichess API

//-----Globals-------------------------
$SAMPLE_SIZE 				= 10; 	//amount of games to sample

//-----Standard Deviation--------------
$SD_POINTS_TOTAL 			= 125; 	//Total amount of points from standard deviation to be assigned towards cheating index

$CD_CONST_MIN_MOVES			= 2; 	//Minimum amount of moves that can be played in a game for it to be counted
$SD_CONST_TRESHOLD			= 1; 	//Standard Deviation / Mean, minimum threshold
$SD_CONST_ADJ 				= 0.25; //Adjustment constant for minimum reachable SD/Mean ratio

//-----Blurs---------------------------
$BL_POINTS_TOTAL 			= 150; 	//Total points from BL -> Cheat Index

$BL_CONST_MIN_MOVES			= 2;

//-----Computer Analysis---------------
$CA_POINTS_TOTAL 			= 75; 	//Total points from CA -> Cheat Index

$CA_CONST_MIN_MOVES			= 2;
$CA_CONST_INACCURACY 		= 1; 	//Rate at which cheat index is diminished for inaccuracy rate
$CA_CONST_MISTAKE 			= 2;
$CA_CONST_BLUNDER 			= 3;

//-----Rating Increase-----------------
$RI_POINTS_TOTAL 			= 50; 	//Total points from RI -> Cheat Index

$RI_CONST_MIN				= 10;
$RI_CONST_MAX				= 200;

//-----Relation To Engine IP-----------
$IP_POINTS_TOTAL 			= 50; 	//Total points from IP -> Cheat Index

//-----Global Functions----------------
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

//-----Standard Deviation Functions----
function SDpoints ( $games, $username ) {
	//Inputs: List of games and target username
	//Output: Points - a score of how suspicious a player is.

	global $SAMPLE_SIZE;
	global $SD_POINTS_TOTAL, $SD_CONST_MIN_MOVES;

	$gamesWithData 	= 0;
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
	return scalePoints( $SD_POINTS_TOTAL, $gamesWithData, $unscaledPoints );
}

function SDpointsForGame ( $moves ) {
	//Input: A list of moves
	//Output: A number between 0 -> 1 of how suspicious that game is

	global $SD_CONST_TRESHOLD, $SD_CONST_ADJ;

	$deviationOverMean 	= SDdeviationOverMean( $moves );
	$output 			= 0;

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

	$sum 		= 0;
	$squareDif 	= 0;
	$moveCount 	= count( $moves ); 

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

//-----Blur Functions------------------
function BLpoints ( $games, $username ) {
	//Input: A list of games with blurs
	//Output: Amount of points for blur rate

	global $SAMPLE_SIZE;
	global $BL_POINTS_TOTAL, $BL_CONST_MIN_MOVES;

	$gamesWithData 	= 0;
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

			if( $moveCount > $BL_CONST_MIN_MOVES ){
				$gamesWithData++;
				$unscaledPoints += BLpointsForGame( $blurs, $moveCount );
			}
		}
	}
	return scalePoints( $BL_POINTS_TOTAL, $gamesWithData, $unscaledPoints );
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

//-----Computer Analysis Functions-----
function CApoints ( $games, $username ) {
	//Input: A list of games with blurs
	//Output: Amount of points for blur rate

	global $SAMPLE_SIZE;
	global $CA_POINTS_TOTAL, $CA_CONST_MIN_MOVES;

	$gamesWithData 	= 0;
	$unscaledPoints = 0;

	//For all of the games
	foreach( $games as $game ) {

		//Determine if the game has the required data
		if( isset( $game['players']['white']['userId'] ) && isset( $game['players']['black']['userId'] ) 
			&& isset( $game['players']['white']['analysis'] ) && isset( $game['players']['black']['analysis'] ) ) {

			//-----Determine What side the player is-----
			if( $game['players']['white']['userId'] == $username ) {
				//player is white
				$analysis 		= $game['players']['white']['analysis'];
				$moveCount 		= count( $game['players']['white']['moveTimes'] );
			} else {
				//player is black
				$analysis 		= $game['players']['black']['analysis'];
				$moveCount 		= count( $game['players']['black']['moveTimes'] );
			}

			if( $moveCount > $CA_CONST_MIN_MOVES ){
				$gamesWithData++;
				$unscaledPoints += CApointsForGame( $analysis, $moveCount );
			}
		}
	}
	return scalePoints( $CA_POINTS_TOTAL, $gamesWithData, $unscaledPoints );
}

function CApointsForGame ( $analysis, $moveCount ) {
	//Input: analysis and move count
	//Output: number from 0->1 for how sus the game is

	global $CA_CONST_INACCURACY, $CA_CONST_MISTAKE, $CA_CONST_BLUNDER;

	$inaccuracyRate = $analysis['inaccuracy'] / $moveCount;
	$mistakeRate 	= $analysis['mistake'] / $moveCount;
	$blunderRate 	= $analysis['blunder'] / $moveCount;

	$output = 1 - abs( $CA_CONST_INACCURACY * $inaccuracyRate + $CA_CONST_MISTAKE * $mistakeRate + $CA_CONST_BLUNDER * $blunderRate );

	//Again, square to cause people with high inaccuracy rates to drop off faster
	return ( $output < 0 ) ? 0 : pow( $output, 2 );
}

//-----Rating Increase Functions-------
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

//-----Relation To Engine Functions----
function IPpoints ( $relatedEngineAccounts ) {
	//Input: List of accounts related to user cheating
	//Output: Points for having relation

	global $IP_POINTS_TOTAL;

	$output = ( count($relatedEngineAccounts) >= 1 ) ? $IP_POINTS_TOTAL : 0;

	return $output;
}

function cheatIndex ( $username, $target = "http://en.lichess.org/api/" ) {
	//Input: A players name
	//Output: A players cheat index

	global $SAMPLE_SIZE, $lichessApiToken;
	global $SD_POINTS_TOTAL, $BL_POINTS_TOTAL, $CA_POINTS_TOTAL, $RI_POINTS_TOTAL, $IP_POINTS_TOTAL;

	$totalAvailable = $SD_POINTS_TOTAL + $BL_POINTS_TOTAL + $CA_POINTS_TOTAL + $RI_POINTS_TOTAL + $IP_POINTS_TOTAL;

	$username 			= str_replace( ' ', '', strtolower( $username ) ); //make username valid

	$gameReq 			= $target."game?username=$username&rated=1&nb=$SAMPLE_SIZE&token=$lichessApiToken"; //api request for player game data
	$playerReq 			= $target."user/$username?token=$lichessApiToken"; //api request for player information

	$gameJson 			= file_get_contents( $gameReq ); //req game data
	$playerJson 		= file_get_contents( $playerReq ); //req player data

	$games 				= json_decode( $gameJson, TRUE )['list']; //decode game data
	$player 			= json_decode( $playerJson, TRUE ); //decode player data


	if ( !empty($games) && !empty($player)){
		//-----Game Functions-------
		$SDpoints = floor( SDpoints( $games, $username ) );
		$BLpoints = floor( BLpoints( $games, $username ) );
		$CApoints = floor( CApoints( $games, $username ) );

		//-----Player Functions-----
		$RIpoints = floor( RIpoints( $player['progress'] ) );
		$IPpoints = floor( IPpoints( $player['knownEnginesSharingIp'] ) );

		//-----Report Outputs-------
		echo "Progress Points:        $RIpoints / $RI_POINTS_TOTAL\n";
		echo "Engine Relation Points: $IPpoints / $IP_POINTS_TOTAL\n\n";

		echo "Deviation Points:       $SDpoints / $SD_POINTS_TOTAL\n";
		echo "Blur Points:            $BLpoints / $BL_POINTS_TOTAL\n";
		echo "Analysis Points:        $CApoints / $CA_POINTS_TOTAL\n\n";

		$totalPoints = $SDpoints + $BLpoints + $CApoints + $RIpoints + $IPpoints;

		echo "Cheat Index:            $totalPoints / $totalAvailable\n";
	}else{
		echo "Player does not exist or does not have any games!\n";
	}	
}

cheatIndex('pablodsilva');