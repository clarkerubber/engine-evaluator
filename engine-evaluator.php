<?php

include("keys.php"); //include keys to lichess API
include("config.php"); //Include settings

//include functions
include("functions/global.php");
include("functions/moveTimes.php");
include("functions/blur.php");
include("functions/computerAnalysis.php");
include("functions/ratingIncrease.php");
include("functions/knownEngineIP.php");

function cheatIndex ( $username, $forceDeep = FALSE, $token = NULL, $target = "http://en.lichess.org/api/" ) {
	//Input: A players name
	//Output: A players cheat index

	global $SAMPLE_SIZE, $lichessApiToken;
	global $POINTS_TOTAL, $DEEP_SEARCH_THRESHOLD, $DEEP_SAMPLE_SIZE, $DEEP_SELECTION_SIZE, $DEEP_MOVE_THRESHOLD;
	global $REPORT_THRESHOLD, $MARK_THRESHOLD;

	if( $token == NULL ){
		$token = $lichessApiToken;
	}

	$gameReq = $target."game?username=$username&rated=1&nb=$SAMPLE_SIZE&token=$token"; //api request for player game data
	$playerReq = $target."user/$username?token=$lichessApiToken"; //api request for player information

	if ( ( $gameJson = file_get_contents( $gameReq ) ) != FALSE
		 && ( $playerJson = file_get_contents( $playerReq ) ) != FALSE ){

		$games = json_decode( $gameJson, TRUE )['list']; //decode game data
		$player = json_decode( $playerJson, TRUE ); //decode player data

		$deepIndex = 0;

		if ( !empty( $games ) && !empty( $player ) ){
			$action = "NOTHING";
			$reportDescription = "";
			$points = array();
			$reportGames = array();
			//-----Game Functions-------
			$points['SD'] = SDpoints( $games, $username );
			$points['BL'] = BLpoints( $games, $username );
			$points['CA'] = CApoints( $games, $username );

			//-----Player Functions-----
			$points['RI'] = RIpoints( $player['progress'] );
			$points['IP'] = IPpoints( $player['knownEnginesSharingIp'] );

			arsort( $points );

			$availableAlloc = 100;
			$cheatIndex = 0;

			foreach ($points as $key => $value) {
				if( $availableAlloc - $POINTS_TOTAL[$key] >= 0 ) {
					$availableAlloc -= $POINTS_TOTAL[$key];
					$cheatIndex += $value;
				}
			}

			if ( $cheatIndex >= $DEEP_SEARCH_THRESHOLD || $forceDeep == TRUE ) {
				$gameReq = $target."game?username=$username&rated=1&nb=$DEEP_SAMPLE_SIZE&token=$token";

				$games = json_decode( file_get_contents( $gameReq ), TRUE )['list'];
				$gameIndexes = array();
				//Process each of the games individually with individual indexes.
				foreach ( $games as $key => $unused ) {
					//this is a bit of a hack so I don't have to make a new set of functions.
					$game = array();
					$game[] = $games[$key];
					$deepPoints['SD'] = SDpoints( $game, $username, $DEEP_MOVE_THRESHOLD );
					$deepPoints['BL'] = BLpoints( $game, $username, $DEEP_MOVE_THRESHOLD );
					$deepPoints['CA'] = CApoints( $game, $username, $DEEP_MOVE_THRESHOLD );

					arsort( $deepPoints );

					$availableAlloc = 100;
					$gameIndex = 0;
					$summary = array(); //summary to be used in report.

					foreach ($deepPoints as $deepPointsKey => $value) {
						if( $availableAlloc - $POINTS_TOTAL[$deepPointsKey] >= 0 ) {
							$availableAlloc -= $POINTS_TOTAL[$deepPointsKey];
							$gameIndex += $value;
							if ($value > 0) {
								if( $deepPointsKey == 'SD' ) {
									$summary[] = sprintf( "Move-Time Deviation: %3.0f/100", 2 * $value );

								} else if ( $deepPointsKey == 'BL' ) {
									$summary[] = sprintf( "Blur Rate: %3.0f/100", 2 * $value );

								} else if ( $deepPointsKey == 'CA' ) {
									$summary[] = sprintf( "Error Rate: %3.0f/100", 2 * $value );
								}
							}
						}
					}
					$summaries[] = sprintf("%3.0f - ", $gameIndex)
						.str_replace( "http://lichess.org/", "http://lichess.org/analyse/", $games[$key]['url'] )."\n     ".implode(", ", $summary);
					$gameIndexes[] = $gameIndex;
				}

				//Sort games by indexes
				array_multisort( $gameIndexes, SORT_DESC, SORT_NUMERIC, $summaries );


				$returnedSampleSize = count( $games );
				$y = 0;

				//Calculate mean

				for($x = 0; $x < $DEEP_SELECTION_SIZE && $x < $returnedSampleSize; $x++ ){
					if($gameIndexes[$x] > 0){ //i.e. there is enough moves played
						$reportDescription .= $summaries[$x]."\n";
						$sum += $gameIndexes[$x];
						$y++;
					}
				}
				$deepIndex = $sum / $y;
				if ( $deepIndex >= $REPORT_THRESHOLD ) {
					$action = "REPORT";
				}
				if ( $deepIndex >= $MARK_THRESHOLD ) {
					$action = "MARK";
				}
				$reportDescription = sprintf("Cheat Index: %3.0f/100,  Deep Index: %3.0f/100\n", $cheatIndex, $deepIndex).$reportDescription;
				//echo $reportDescription;
			}

			//-----Report Outputs-------
			$outputArray = array(
				"userId" => $username,
				"cheatIndex" => floor($cheatIndex),
				"deepIndex" => floor($deepIndex),
				"action" => $action,
				"reportDescription" => $reportDescription,
				"moveTime" => floor( 100 * $points['SD'] / $POINTS_TOTAL['SD'] ),
				"blur" => floor( 100 * $points['BL'] / $POINTS_TOTAL['BL'] ),
				"computerAnalysis" => floor( 100 * $points['CA'] / $POINTS_TOTAL['CA'] ),
				"progress" => floor( 100 * $points['RI'] / $POINTS_TOTAL['RI'] ),
				"knownEngineIP" => floor( 100 * $points['IP'] / $POINTS_TOTAL['IP'] ),
				"Error" => 0
				);

			$output = json_encode($outputArray);

		}else{
			$output = '{"Error" : 2}';
		}
	} else {
		$output = '{"Error" : 1}';
	}
	return $output;
}

$output = "";

if( isset( $argv[1] ) && isset( $argv[2] ) && isset( $argv[3] ) && isset( $argv[4] ) ) {
	$output = cheatIndex( strtolower( $argv[1] ), $argv[2], $argv[3], $argv[4] );
} else if ( isset( $argv[1] ) && isset( $argv[2] ) && isset( $argv[3] ) ) {
	$output = cheatIndex( strtolower( $argv[1] ), $argv[2], $argv[3] );
} else if ( isset( $argv[1] ) && isset( $argv[2] ) ) {
	$output = cheatIndex( strtolower( $argv[1] ), $argv[2] );
} else if ( isset( $argv[1] ) ) {
	$output = cheatIndex( strtolower( $argv[1] ) );
}

echo $output;
