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
include("functions/holdAlert.php");

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

		$deepIndex = null;

		if ( !empty( $games ) && !empty( $player ) ){
			$action = "NOTHING";
			$points = array();
			$reportGames = array();
			//-----Game Functions-------
			$points['SD'] = SDpoints( $games, $username );
			$points['BL'] = BLpoints( $games, $username );
			$points['CA'] = CApoints( $games, $username );
			$points['HA'] = HApoints( $games, $username );

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
					$deepPoints['HA'] = HApoints( $game, $username );

					arsort( $deepPoints );

					$availableAlloc = 100;
					$gameIndex = 0;

					foreach ($deepPoints as $deepPointsKey => $value) {
						if( $availableAlloc - $POINTS_TOTAL[$deepPointsKey] >= 0 ) {
							$availableAlloc -= $POINTS_TOTAL[$deepPointsKey];
							$gameIndex += $value;
						}
					}
                    $summaries[] = array( "url" => str_replace( "lichess.org/", "lichess.org/", $games[$key]['url'] ),
                    	"moveTime" => floor( 2 * $deepPoints['SD'] ),
                    	"blur" => floor( 2 * $deepPoints['BL'] ), 
                    	"error" => floor( 2 * $deepPoints['CA'] ),
                    	"holdAlert" => floor( 2 * $deepPoints['HA'] ) 
                    );
					$gameIndexes[] = $gameIndex;
				}

				//Sort games by indexes
				array_multisort( $gameIndexes, SORT_DESC, SORT_NUMERIC, $summaries );


				$returnedSampleSize = count( $games );
				$y = 0;

				//Calculate mean

				$sum = 0;
				for($x = 0; $x < $DEEP_SELECTION_SIZE && $x < $returnedSampleSize; $x++ ){
					if($gameIndexes[$x] > 0){ //i.e. there is enough moves played
						$reportGames[] = $summaries[$x];
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
			}

			//-----Report Outputs-------
			$outputArray = array(
				"userId" => $username,
				"cheatIndex" => floor($cheatIndex),
				"deepIndex" => $deepIndex ? floor($deepIndex) : null,
				"action" => $action,
				"games" => $reportGames,
				"moveTime" => floor( 100 * $points['SD'] / $POINTS_TOTAL['SD'] ),
				"blur" => floor( 100 * $points['BL'] / $POINTS_TOTAL['BL'] ),
				"computerAnalysis" => floor( 100 * $points['CA'] / $POINTS_TOTAL['CA'] ),
				"progress" => floor( 100 * $points['RI'] / $POINTS_TOTAL['RI'] ),
				"knownEngineIP" => floor( 100 * $points['IP'] / $POINTS_TOTAL['IP'] )
				);

			$output = json_encode($outputArray);

		}else{
			exit(2);
		}
	} else {
		exit(1);
	}
	return $output;
}

$output = "";

if( isset( $argv[4] ) ) {
	$output = cheatIndex( strtolower( $argv[1] ), $argv[2], $argv[3], $argv[4] );
} else if ( isset( $argv[3] ) ) {
	$output = cheatIndex( strtolower( $argv[1] ), $argv[2], $argv[3] );
} else if ( isset( $argv[2] ) ) {
	$output = cheatIndex( strtolower( $argv[1] ), $argv[2] );
} else if ( isset( $argv[1] ) ) {
	$output = cheatIndex( strtolower( $argv[1] ) );
} else {
    error_log("Missing username parameter");
    exit(3);
}

echo $output;
exit(0);
