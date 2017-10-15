<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;

class TwitchUser implements Arrayable
{

	public $live;
	protected $attributes = [];
	protected $dates = [
		'expired_at',
	];
//	protected $stream;
	public $exists = false;


	public function __construct(string $login)
	{
		$this->setAttribute('login', $login);
	}


	public function hydrate(array $attributes)
	{
		foreach ($attributes as $key => $val) {

			$this->setAttribute($key, $val);

		}

		if (!$this->has('expired_at')) {
			$this->setAttributes('expired_at', Carbon::now());
		}

		$this->exists = true;

	}


	protected function setAttribute(string $name, $value)
	{
		if (array_search($name, $this->dates, true) !== false) {

			if (!is_a($value, Carbon::class)) {

				$value = Carbon::createFromTimestamp($value);

			}

		}

		array_set($this->attributes, $name, $value);
	}


	public function __get($name)
	{
		if (!$this->has($name))
			return null;

		return $this->attributes[ $name ];
	}


	public function has($name)
	{
		return array_key_exists($name, $this->attributes);
	}


	public function setExpiredAt(Carbon $date)
	{
		$this->setAttribute('expired_at', $date);
	}


	public function cacheExpired()
	{
		return !$this->exists || $this->expired_at->isPast();
	}


	public function toArray(): array
	{
		$ar = $this->attributes;

		foreach ($this->dates as $key) {

			$ar[ $key ] = $ar[ $key ]->timestamp;

		}

		return $ar;

	}


	public function toJSON(): string
	{

		return json_encode($this->toArray());
	}

}