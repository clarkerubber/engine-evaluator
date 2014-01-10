<?php

function IPpoints ( $relatedEngineAccounts ) {
	//Input: List of accounts related to user cheating
	//Output: Points for having relation

	global $IP_POINTS_TOTAL;

	$output = ( count($relatedEngineAccounts) >= 1 ) ? $IP_POINTS_TOTAL : 0;

	return $output;
}