<?php

namespace App;

class TwitchUser extends TwitchModel
{

	protected $primaryKey = 'login';


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

}