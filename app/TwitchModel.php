<?php


namespace App;


use ArrayAccess;
use Carbon\Carbon;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JsonSerializable;
use Psr\SimpleCache\CacheInterface;
use Serializable;

class TwitchModel implements ArrayAccess, Arrayable, Jsonable, Serializable, JsonSerializable
{

	protected $cached = false;

	protected $modified = true;

	protected $cache_driver = null;

	protected $cache_lifetime = 3600;

	protected $primaryKey = 'id';

	protected $key = null;

	protected $attributes = [];

	protected $dates = [
		'cached_at',
		'expired_at',
	];

	protected $cache_prefix = '';

	protected $relations = [];


	public function __construct($params = null)
	{
		if (!is_null($params))
			$this->hydrate($params);

	}


	protected static function newInstance(string $key)
	{
		$className = get_called_class();
		$obj = new $className();
		$obj->setKey($key);

		return $obj;
	}


	public static function find(string $name): TwitchModel
	{
		$object = self::newInstance($name);

		return $object->load();
	}


	/**
	 * @param array $names
	 * @return \Illuminate\Support\Collection
	 */
	public static function findMany(array $names)
	{
		$c = collect();
		foreach ($names as $name) {

			$obj = self::newInstance($name);

			$obj->load();

			$c->push($obj);

		}

		return $c;
	}


	public static function getExpired(Collection $models): Collection
	{
		return $models->filter(function (TwitchModel $model, $key) {
			return $model->isExpired();
		});
	}


	public function forget()
	{
		$this->getCacheDriver()->forget($this->getCacheIndex());
		$this->cached = false;
	}


	public function getCacheDriver(): Repository
	{
		return Cache::store($this->cache_driver);
	}


	public function getCacheIndex(): string
	{
		return $this->cache_prefix . class_basename(get_called_class()) . $this->getKey();
	}


	public function hasOne(string $related, string $foreignKey, string $localKey): TwitchRelation
	{

		$m = new $related();

		$m->setAttribute($foreignKey, $this->getAttribute($localKey));

		return new TwitchRelation($this, $m);
	}


	public function hydrate(array $attributes)
	{
		foreach ($attributes as $key => $val) {

			$this->setAttribute($key, $val);

		}
	}


	/**
	 * @return TwitchModel
	 */
	public function load(): TwitchModel
	{
		$data = $this->getCacheDriver()->get($this->getCacheIndex());

		if (empty($data))
			return $this;

		$data = unserialize($data);

		if (!is_array($data))
			return $this;

		$this->hydrate($data);
		$this->cached = true;
		$this->modified = false;

		return $this;
	}


	public function save($withRelation = false)
	{
		if ($withRelation) {
			foreach ($this->relations as $relation)
				$relation->save();
		}

		if (!$this->modified)
			return;

		var_dump(class_basename(get_called_class()));

		$ttl = $this->cache_lifetime;

		$this->setAttribute('cached_at', Carbon::now());
		$this->setAttribute('expired_at', Carbon::now()->addSeconds($ttl));

		$this->getCacheDriver()->put($this->getCacheIndex(), serialize($this->toArray()), $ttl);

		$this->cached = true;
		$this->modified = false;
	}


	public function getAttribute(string $key)
	{
		if (!$key) {
			return null;
		}

		if (!$this->hasAttribute($key))
			return null;

		return $this->attributes[ $key ];

	}


	public function setAttribute(string $name, $value)
	{
		if (array_search($name, $this->dates, true) !== false) {

			if (!is_a($value, Carbon::class)) {

				$value = Carbon::createFromTimestamp($value);

			}

		}

		array_set($this->attributes, $name, $value);
		$this->modified = true;

		return $this;
	}


	public function hasAttribute($key)
	{
		return array_key_exists($key, $this->attributes);
	}


	public function getKeyName()
	{
		return $this->primaryKey;
	}


	public function getKey()
	{
		return $this->getAttribute($this->getKeyName());
	}


	public function setKey($name)
	{
		$this->setAttribute($this->getKeyName(), $name);
	}


	public function isCached()
	{
		if ($this->isExpired())
			$this->cached = false;

		return $this->cached;
	}


	public function isExpired()
	{
		return !$this->hasAttribute('expired_at') || $this->getAttribute('expired_at')->isPast();
	}


	public function ttl()
	{
		return $this->hasAttribute('expired_at') ? $this->getAttribute('expired_at')->diffInSeconds() : 0;
	}


	public function getRelation($key)
	{
		return $this->relations[ $key ];
	}


	public function setRelation($key, $value)
	{
		$this->relations[ $key ] = $value;

		return $this;
	}


	public function relationLoaded($key)
	{
		return array_key_exists($key, $this->relations);
	}


	public function __get($key)
	{
		if ($this->hasAttribute($key))
			return $this->getAttribute($key);

		if ($this->relationLoaded($key)) {

			return $this->getRelation($key);

		} else if (method_exists($this, $key)) {

			$this->setRelation($key, $this->$key());

			return $this->getRelation($key);
		}

		return null;
	}


	public function __set(string $key, $name)
	{
		return $this->setAttribute($key, $name);
	}


	/**
	 * Specify data which should be serialized to JSON
	 * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return mixed data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	function jsonSerialize()
	{
		return $this->toArray();
	}


	public function toJson($option = 0): string
	{
		return json_encode($this, $option);
	}


	public function toArray(): array
	{
		$ar = $this->attributes;

		foreach ($this->dates as $key)
			$ar[ $key ] = $ar[ $key ]->timestamp;

		return $ar;

	}


	/**
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 * @since 5.1.0
	 */
	public function serialize()
	{
		return $this->serialize($this->attributes);
	}


	/**
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 * @param string $serialized <p>
	 * The string representation of the object.
	 * </p>
	 * @return void
	 * @since 5.1.0
	 */
	public function unserialize($serialized)
	{
		$this->hydrate(unserialize($serialized));
	}


	/**
	 * Whether a offset exists
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param mixed $offset <p>
	 * An offset to check for.
	 * </p>
	 * @return boolean true on success or false on failure.
	 * </p>
	 * <p>
	 * The return value will be casted to boolean if non-boolean was returned.
	 * @since 5.0.0
	 */
	public function offsetExists($offset)
	{
		return !is_null($this->getAttribute($offset));
	}


	/**
	 * Offset to retrieve
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 * @param mixed $offset <p>
	 * The offset to retrieve.
	 * </p>
	 * @return mixed Can return all value types.
	 * @since 5.0.0
	 */
	public function offsetGet($offset)
	{
		return $this->getAttribute($offset);
	}


	/**
	 * Offset to set
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 * @param mixed $offset <p>
	 * The offset to assign the value to.
	 * </p>
	 * @param mixed $value <p>
	 * The value to set.
	 * </p>
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetSet($offset, $value)
	{
		$this->setAttribute($offset, $value);
	}


	/**
	 * Offset to unset
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param mixed $offset <p>
	 * The offset to unset.
	 * </p>
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetUnset($offset)
	{
		unset($this->attributes[ $offset ]);
	}
}