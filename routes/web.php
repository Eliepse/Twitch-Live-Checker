<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {

//	echo '<pre>';

	$start_time = microtime(true);

	$api = new \App\TwitchAPI();

	$status = $api->updateStreamsStatus();

//	echo "Execution time : " . round(microtime(true) - $start_time, 3) . "ms.<br/><br/>";

//	foreach ($status as $login => $value) {
//		echo "$login est " . ($value ? 'ONLINE.' : 'offline.') . '<br/>';
//	}

//	echo '</pre>';

	return response()->json([
		'data'           => $status,
		'execution_time' => round(microtime(true) - $start_time, 3),
	]);

});
