<?php


require dirname(__DIR__) . '/vendor/autoload.php'; // Autoload files using Composer autoload

use j3j5\LastfmApio;
$api = new LastfmApio();
$username = 'lapegatina';
$api->user_getweeklyartistchart(array('user' => $username), FALSE, TRUE);
$api->user_getweeklyartistchart(array('user' => $username, 'from' => 1210507200, 'to' => 1211112000), FALSE, TRUE);
$api->user_getweeklyartistchart(array('user' => $username, 'from' => 1217764800, 'to' => 1218369600), FALSE, TRUE);
$api->user_getweeklyartistchart(array('user' => $username, 'from' => 1232280000, 'to' => 1232884800), FALSE, TRUE);

// The response to all concurrent requests is return as an array using as key a string of the parameters joined by '.'
$responses = $api->run_multi_requests();
foreach($responses AS $response) {
	$allresponseresults = isset($response->weeklyartistchart->artist) ? $response->weeklyartistchart->artist : array();
	print count($allresponseresults) . " artists found." . PHP_EOL;
}
