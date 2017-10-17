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

		$request = $this->requestBuilder('GET', 'https://api.twitch.tv/helix/users', [
			'login' => $users->pluck('login')->toArray(),
		], $this->getToken());

		$response = $this->sendRequest($request);
		$content = $response->getBody()->getContents();
		$content_array = json_decode($content, true);

		if (!is_array($content_array['data']))
			return $users;

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


	public function fetchUsersStreamData(Collection $users)
	{
		// If no users, stop
		if ($users->isEmpty())
			return $users;

		$request = $this->requestBuilder('GET', 'https://api.twitch.tv/helix/streams', [
			'user_id' => $users->pluck('id')->toArray(),
		], $this->getToken());

		$response = $this->sendRequest($request);
		$content = $response->getBody()->getContents();
		$content_array = json_decode($content, true);


		if (!is_array($content_array['data']))
			return $users;

		$streams = collect($content_array['data']);

		foreach ($users as $user) {

			$stream = $streams->where('user_id', $user->id)->first();

			if (empty($stream)) {

				$user->stream()->off();

			} else {

				$user->stream()->hydrate($stream);
				$user->stream()->on();

			}

			$user->save(true);

		}

		return $users;

	}


	protected function joinRequestArrayValue(string $key, array $values): string
	{
		$vals = [];

		foreach ($values as $val)
			$vals[] = rawurlencode($val);

		return join('&' . rawurlencode($key) . '=', $vals);
	}

}