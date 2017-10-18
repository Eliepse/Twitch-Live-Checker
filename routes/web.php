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

/*
 * Exemple to fetch all allowed users+streams and refresh all expired
 * */
/*$router->get('/streams', function () {

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
		$data[ $user->login ] = $user->stream()->toArray();
	}

	return response()->json([
		'data'           => $data,
		'timeout'        => intval(env('APP_REQUEST_TIMEOUT', 60)),
		'execution_time' => round(microtime(true) - $start_time, 3),
	]);

});*/

$router->get('/streams/{user_login}/statut', function ($user_login) {

	$start_time = microtime(true);

	$allowed = collect(explode(',', env('TWITCH_USERS')))->filter(function ($item) use ($user_login) {
		return trim($item) === trim($user_login);
	});

	if (empty($allowed))
		return response()->json([
			'data'           => [],
			'message'        => [
				'text' => 'User login not allowed',
				'type' => 'error',
			],
			'execution_time' => round(microtime(true) - $start_time, 3),
		]);


	$api = new \App\TwitchAPI();
	$user = \App\TwitchUser::find($user_login);

	if ($user->isExpired())
		$api->fetchUsersData(collect([$user]));

	if ($user->stream()->isExpired())
		$api->fetchUsersStreamData(collect([$user]));

	return response()->json([
		'data'           => [
			$user->getKey() => $user->stream()->isLive,
		],
		'timeout'        => intval(env('APP_REQUEST_TIMEOUT', 60)),
		'execution_time' => round(microtime(true) - $start_time, 3),
	]);

});