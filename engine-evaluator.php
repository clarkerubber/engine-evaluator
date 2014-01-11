<?php

include("keys.gitignore/keys.php"); //include keys to lichess API
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
	global $POINTS_TOTAL;

	if( $token == NULL ){
		$token = $lichessApiToken;
	}

	$gameReq = $target."game?username=$username&rated=1&nb=$SAMPLE_SIZE&token=$token"; //api request for player game data
	$playerReq = $target."user/$username?token=$lichessApiToken"; //api request for player information

	if( functionalURL( $gameReq ) && functionalURL( $playerReq ) ){
		$gameJson = file_get_contents( $gameReq ); //req game data
		$playerJson = file_get_contents( $playerReq ); //req player data

		$games = json_decode( $gameJson, TRUE )['list']; //decode game data
		$player = json_decode( $playerJson, TRUE ); //decode player data

		if ( !empty($games) && !empty($player)){
			$points = array();
			//-----Game Functions-------
			$points['SD'] = SDpoints( $games, $username );
			$points['BL'] = BLpoints( $games, $username );
			$points['CA'] = CApoints( $games, $username );

			//-----Player Functions-----
			$points['RI'] = RIpoints( $player['progress'] );
			$points['IP'] = IPpoints( $player['knownEnginesSharingIp'] );

			arsort($points);

			$availableAlloc = 100;
			$cheatIndex = 0;

			foreach ($points as $key => $value) {
				if( $availableAlloc - $POINTS_TOTAL[$key] >= 0 ) {
					$availableAlloc -= $POINTS_TOTAL[$key];
					$cheatIndex += $value;
				}
			}

			//-----Report Outputs-------
			$format = '{"userId":"%s","cheatIndex":%3.2f,"moveTime":%3.2f,"blur":%3.2f,"computerAnalysis":%3.2f,"progress":%3.2f,"knownEngineIP":%3.2f,"Error":0}';
			$output = sprintf($format, $username, floor($cheatIndex), 
				$points['SD'],
				$points['BL'],
				$points['CA'],
				$points['RI'],
				$points['IP']);


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