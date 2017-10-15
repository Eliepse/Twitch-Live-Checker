<?php

namespace App;

use Carbon\Carbon;

use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Collection;
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
		$this->getUsers();
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


	private function fetchUsersData($users = null)
	{

		if (is_null($users))
			$users = $this->getUsers(true);

		// We exclude users without login
		$users = $users->filter(function ($user) {
			return $user->has('login');
		});

		// If no users, return
		if (empty($users))
			return;

		$logins = [];

		foreach ($users as $user) {
			$logins[] = $user->login;
		}

		$request = $this->requestBuilder('GET', 'https://api.twitch.tv/helix/users', [
			'login' => $logins,
		], $this->getToken());

		$response = $this->sendRequest($request);
		$content = $response->getBody()->getContents();
		$content_array = json_decode($content, true);
		$dt_now = Carbon::now()->subSeconds(1); // We remove a second to be sure it's outdated

		if (!is_array($content_array['data'])) {
			return;
		}

		foreach ($content_array['data'] as $user) {

			$t_user = $users->first(function ($tuser) use ($user) {
				return $tuser->login === $user['login'];
			});

			if (empty($t_user))
				continue;


			$t_user->hydrate(array_merge($user, [
				'expired_at' => $dt_now->copy(),
			]));

		}

		$this->cacheUsers($users);

	}


	private function getStreamsStatus(RedisClient $redis, $users = null)
	{

		$prefix = env('CACHE_KEY_PREFIX', 'twitch_') . 'u_';

		if (is_null($users))
			$users = $this->getUsers(true);

		$streams = [];

		foreach ($users as $user) {
			$val = $redis->get($prefix . $user->login);
//			dd($val);
			$streams[ $user->login ] = !is_null($val) ? boolval($val) : null;
		}

		return $streams;

	}


	private function fetchUsersStreamsStatut(RedisClient $redis, $users = null, bool $hardReset = false)
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

	}


	/**
	 * @param bool $withCache
	 * @return Collection
	 */
	public function getUsers(bool $withCache = false): Collection
	{
		if (!empty($this->users)) {

			if ($withCache && !$this->user_cache_loaded)
				$this->loadUsersCache();

			return $this->users;
		}

		$users = new Collection();
		$users_login = explode(',', env('TWITCH_USERS', ''));

		foreach ($users_login as $user_login) {

			$user_login = trim($user_login);

			if (empty($user_login))
				continue;

			$users->push(new TwitchUser($user_login));

		}

		$this->users = $users;

		if ($withCache)
			$this->loadUsersCache();

		return $this->users;

	}


	private function cacheUsers(Collection $twitchUsers)
	{
		$prefix = env('CACHE_KEY_PREFIX', 'twitch_');
		$ttl = env('CACHE_USER_LIFETIME', 600);
		$path = storage_path('app/' . $prefix . 'users_cache/');

		if (!is_dir($path))
			mkdir($path, 0774);

		foreach ($twitchUsers as $twitchUser) {

			if ($twitchUser->cacheExpired()) {

				$twitchUser->setExpiredAt(Carbon::now()->addSeconds($ttl));

				file_put_contents($path . $twitchUser->login . '.json', $twitchUser->toJson());
			}

		}
	}


	private function cacheStreamsStatut(RedisClient $redis, TwitchUser $user, bool $statut)
	{

		$prefix = env('CACHE_KEY_PREFIX', 'twitch_') . 'u_';
		$ttl = intval(env('CACHE_STREAM_STATUT_LIFETIME', 60));

		$redis->setex($prefix . $user->login, $ttl, $statut);

	}


	private function loadUsersCache()
	{
		$prefix = env('CACHE_KEY_PREFIX', 'twitch_');
		$path = $path = storage_path('app/' . $prefix . 'users_cache/');

		foreach ($this->users as $user) {

			$filepath = $path . $user->login . '.json';

			if (file_exists($filepath)) {

				$user->hydrate(json_decode(file_get_contents($filepath), true));

			}
		}
	}


	private function getExpiredUsers()
	{
		return $this->getUsers(true)->filter(function ($user) {
			return $user->cacheExpired();
		});
	}


	protected function joinRequestArrayValue(string $key, array $values): string
	{
		$vals = [];

		foreach ($values as $val)
			$vals[] = rawurlencode($val);

		return join('&' . rawurlencode($key) . '=', $vals);
	}

}