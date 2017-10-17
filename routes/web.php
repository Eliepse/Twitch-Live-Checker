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

	$start_time = microtime(true);

	$api = new \App\TwitchAPI();
	$users = \App\TwitchUser::all();

	$api->fetchUsersData(
		$users->filter(function (\App\TwitchUser $user) {
			return $user->isExpired();
		})
	);

	$stream_to_update = $users->filter(function (\App\TwitchUser $user) {
		return $user->stream()->isExpired();
	});

	$api->fetchUsersStreamData($stream_to_update);

	$data = [];

	foreach ($users as $user) {
		$data[ $user->login ] = $user->stream()->statut();
	}

	return response()->json([
		'data'            => $data,
		'updated_streams' => $stream_to_update->pluck('login')->toArray(),
		'execution_time'  => round(microtime(true) - $start_time, 3),
	]);

});