<?php

function IPpoints ( $relatedEngineAccounts ) {
	//Input: List of accounts related to user cheating
	//Output: Points for having relation

	global $POINTS_TOTAL;

	$output = ( count($relatedEngineAccounts) >= 1 ) ? $POINTS_TOTAL['IP'] : 0;

	return $output;
}