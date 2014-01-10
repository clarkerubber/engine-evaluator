<?php

//-----Globals-------------------------
$SAMPLE_SIZE		= 10; 	//amount of games to sample

//-----Standard Deviation--------------
$POINTS_TOTAL['SD']	= 50; 	//Total amount of points from standard deviation to be assigned towards cheating index

$CD_CONST_MIN_MOVES	= 2; 	//Minimum amount of moves that can be played in a game for it to be counted
$SD_CONST_TRESHOLD	= 1; 	//Standard Deviation / Mean, minimum threshold
$SD_CONST_ADJ		= 0.25; //Adjustment constant for minimum reachable SD/Mean ratio

//-----Blurs---------------------------
$POINTS_TOTAL['BL']	= 50; 	//Total points from BL -> Cheat Index

$BL_CONST_MIN_MOVES	= 2;

//-----Computer Analysis---------------
$POINTS_TOTAL['CA']	= 50; 	//Total points from CA -> Cheat Index

$CA_CONST_MIN_MOVES	= 2;
$CA_CONST_INACCURACY = 1; 	//Rate at which cheat index is diminished for inaccuracy rate
$CA_CONST_MISTAKE	= 2;
$CA_CONST_BLUNDER	= 3;

//-----Rating Increase-----------------
$POINTS_TOTAL['RI']	= 25; 	//Total points from RI -> Cheat Index

$RI_CONST_MIN		= 10;
$RI_CONST_MAX		= 200;

//-----Relation To Engine IP-----------
$POINTS_TOTAL['IP']	= 25; 	//Total points from IP -> Cheat Index