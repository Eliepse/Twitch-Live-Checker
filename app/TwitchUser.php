<?php

namespace App;

class TwitchUser extends TwitchModel
{

	protected $primaryKey = 'login';

	protected $stream;


	public function __construct($params = null)
	{
		$this->cache_lifetime = intval(env('CACHE_USER_LIFETIME', 3600));
		parent::__construct($params);
	}


	/**
	 * @return \Illuminate\Support\Collection|null
	 */
	public static function all()
	{
		$names = env('TWITCH_USERS', null);

		if (is_null($names))
			return null;

		$names = preg_split('/\s*,\s*/', $names);

		return self::findMany($names);
	}


	public static function flushAll()
	{

		$names = env('TWITCH_USERS', null);

		if (is_null($names))
			return null;

		$names = preg_split('/\s*,\s*/', $names);

		foreach ($names as $login) {

			$obj = new TwitchUser();
			$obj->setKey($login);
			$obj->forget();

		}

	}


	public function stream(): TwitchStream
	{

		if (empty($this->relations['stream']))
			$this->relations['stream'] = TwitchStream::find($this->getKey());

		if (empty($this->relations['stream']))
			$this->relations['stream'] = (new TwitchStream())->setAttribute('user_' . $this->getKeyName(), $this->getKey());


		return $this->relations['stream'];
	}

}