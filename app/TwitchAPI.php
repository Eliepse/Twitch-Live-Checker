<?php

namespace App;

use Carbon\Carbon;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Predis\Client as RedisClient;

class TwitchAPI
{

	use OAuthTokenCredentialsTrait;
	use OAuthCredentialsRequestTrait;

	private $users;
	private $user_cache_loaded = false;


	public function __construct()
	{
		$this->app_client_id = env('TWITCH_APP_ID', null);
		$this->app_client_secret = env('TWITCH_APP_SECRET', null);
		$this->token_request_url = 'https://api.twitch.tv/kraken/oauth2/token';

		// Instanciate users
//		$this->getUsers();
	}


	public function updateStreamsStatus()
	{

		$expired_users = $this->getExpiredUsers();

		if ($expired_users->count() > 0) {

			$this->fetchUsersData($expired_users);

		}


		$redis = new RedisClient();

		// We get the statut for all streams but without hard reset (we only update expired values)
		$this->fetchUsersStreamsStatut($redis, $this->getUsers(true), false);

		return $this->getStreamsStatus($redis);


	}


	public function fetchUsersData(Collection $users)
	{

		// If no users, stop
		if ($users->isEmpty())
			return $users;

		$logins = [];

		foreach ($users as $user) {
			if ($user->hasAttribute('login'))
				$logins[] = $user->login;
		}

		$request = $this->requestBuilder('GET', 'https://api.twitch.tv/helix/users', [
			'login' => $users->pluck('login'),
		], $this->getToken());

		$response = $this->sendRequest($request);
		$content = $response->getBody()->getContents();
		$content_array = json_decode($content, true);

		if (!is_array($content_array['data'])) {
			return $users;
		}

		foreach ($content_array['data'] as $user) {

			$t_user = $users->first(function ($tuser) use ($user) {

				if ($tuser->hasAttribute('login'))
					return $tuser->login === $user['login'];

			});

			if (empty($t_user))
				continue;

			$t_user->hydrate($user);
			$t_user->save();

		}

		return $users;

	}


	/*private function fetchUsersStreamsStatut(RedisClient $redis, $users = null, bool $hardReset = false)
	{

		$prefix = env('CACHE_KEY_PREFIX', 'twitch_') . 'u_';

		if (is_null($users))
			$users = $this->getUsers(true);

		// If no hard reset, we remove unexpired values from query
		if (!$hardReset) {

			$users = $users->filter(function ($user) use ($redis, $prefix) {

				return !boolval($redis->exists($prefix . $user->login));

			});

		}


		// We exclude users without id
		$users = $users->filter(function ($user) {
			return $user->has('id');
		});

		// If no users left, we stop
		if (empty($users))
			return;

		$users_id = [];

		foreach ($users as $user)
			$users_id[] = $user->id;

		$request = $this->requestBuilder('GET', 'https://api.twitch.tv/helix/streams', [
			'user_id' => $users_id,
		], $this->getToken());

		$response = $this->sendRequest($request);
		$content = $response->getBody()->getContents();
		$content_array = json_decode($content, true);

		if (!is_array($content_array['data'])) {

			foreach ($users as $user) {
				$this->cacheStreamsStatut($redis, $user, false);
			}

		} else {

			foreach ($users as $user) {

				$user_id = $user->id;

				$stream = array_first($content_array['data'], function ($stream) use ($user_id) {
					return $stream['user_id'] === $user_id;
				});

				if (!empty($stream))
					$this->cacheStreamsStatut($redis, $user, true);
				else
					$this->cacheStreamsStatut($redis, $user, false);

			}

		}

	}*/


	protected function joinRequestArrayValue(string $key, array $values): string
	{
		$vals = [];

		foreach ($values as $val)
			$vals[] = rawurlencode($val);

		return join('&' . rawurlencode($key) . '=', $vals);
	}

}