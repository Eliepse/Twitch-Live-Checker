<?php


namespace App;


class TwitchStream extends TwitchModel
{

	protected $primaryKey = 'user_login';

	protected $user;


	public function __construct($params = null)
	{

		$this->cache_lifetime = intval(env('CACHE_STREAM_LIFETIME', 60));

		if (is_array($params))
			parent::__construct($params);
		else if (is_a($params, TwitchUser::class)) {
			$this->makeFromUser($params);
		}
	}


	public function makeFromUser(TwitchUser $user)
	{
		$this->setAttribute($this->getKeyName(), $user->getAttribute('user_' . $user->getKeyName()));
		$this->user = $user;
	}


	/**
	 * @return \Illuminate\Support\Collection|null
	 */
	public static function all()
	{
		$users = TwitchUser::all();

		if ($users->isEmpty())
			return null;

		$streams = collect();

		foreach ($users as $user) {
			$streams->push(new TwitchStream($user));
		}

		return $streams;

	}


	public function user()
	{
		if (empty($this->user)) {
			$this->user = TwitchUser::find($this->getAttribute('user_' . (new TwitchUser())->getKeyName()));
		}

		return $this->user;
	}


	public function on()
	{
		$this->setAttribute('isLive', true);
	}


	public function off()
	{
		$id = $this->getKey();
		$user_id_key = (new TwitchUser())->getKeyName();
		$user = $this->getAttribute('user_' . $user_id_key);

		$this->attributes = [];

		$this->setAttribute($this->getKeyName(), $id);
		$this->setAttribute('isLive', false);
		$this->setAttribute('user_' . $user_id_key, $user);
	}


	public function statut()
	{
		return $this->getAttribute('isLive');
	}

}