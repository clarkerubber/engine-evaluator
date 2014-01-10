<?php

include("../keys/keys.php"); //include keys to lichess API
include("config.php"); //Include settings

//include functions
include("functions/global.php");
include("functions/moveTimes.php");
include("functions/blur.php");
include("functions/computerAnalysis.php");
include("functions/ratingIncrease.php");
include("functions/knownEngineIP.php");

function cheatIndex ( $username, $token = NULL, $target = "http://en.lichess.org/api/" ) {
	//Input: A players name
	//Output: A players cheat index

	global $SAMPLE_SIZE, $lichessApiToken;
	global $SD_POINTS_TOTAL, $BL_POINTS_TOTAL, $CA_POINTS_TOTAL, $RI_POINTS_TOTAL, $IP_POINTS_TOTAL;

	if( $token == NULL ){
		$token = $lichessApiToken;
	}

	$totalAvailable = $SD_POINTS_TOTAL + $BL_POINTS_TOTAL + $CA_POINTS_TOTAL + $RI_POINTS_TOTAL + $IP_POINTS_TOTAL;

	$gameReq 			= $target."game?username=$username&rated=1&nb=$SAMPLE_SIZE&token=$token"; //api request for player game data
	$playerReq 			= $target."user/$username?token=$lichessApiToken"; //api request for player information

	if( functionalURL( $gameReq ) && functionalURL( $playerReq ) ){
		$gameJson 			= file_get_contents( $gameReq ); //req game data
		$playerJson 		= file_get_contents( $playerReq ); //req player data

		$games 				= json_decode( $gameJson, TRUE )['list']; //decode game data
		$player 			= json_decode( $playerJson, TRUE ); //decode player data

		if ( !empty($games) && !empty($player)){
			//-----Game Functions-------
			$SDpoints = SDpoints( $games, $username );
			$BLpoints = BLpoints( $games, $username );
			$CApoints = CApoints( $games, $username );

			//-----Player Functions-----
			$RIpoints = RIpoints( $player['progress'] );
			$IPpoints = IPpoints( $player['knownEnginesSharingIp'] );

			$cheatIndex = 100 * ( $SDpoints + $BLpoints + $CApoints + $RIpoints + $IPpoints ) / $totalAvailable;

			//-----Report Outputs-------
			$format = '{
	"userId" : %s,
	"cheatIndex" : %3.2f,
	"moveTime" : %3.2f,
	"blur" : %3.2f,
	"computerAnalysis" : %3.2f,
	"progress" : %3.2f,
	"knownEngineIP" : %3.2f,
	"Error" : 0
}';
			$output = sprintf($format, $username, $cheatIndex, 
				100 * $SDpoints / $SD_POINTS_TOTAL, 
				100 * $BLpoints / $BL_POINTS_TOTAL,
				100 * $CApoints / $CA_POINTS_TOTAL,
				100 * $RIpoints / $RI_POINTS_TOTAL,
				100 * $IPpoints / $IP_POINTS_TOTAL);


		}else{
			$output = '{"Error" : 2}';
		}
	} else {
		$output = '{"Error" : 1}';
	}
	return $output;
}

//When calling from command line. Argv[1] is the username, and Argv[2] is the
//optional token to access hidden information.

if( isset( $argv[1] ) && isset( $argv[2] ) ){
	$output = cheatIndex( strtolower( $argv[1] ), $argv[2] );
} else if ( isset( $argv[1] ) ){
	$output = cheatIndex( strtolower( $argv[1] ) );
}

echo $output;