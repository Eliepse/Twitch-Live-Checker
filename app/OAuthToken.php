<?php


namespace App;


use Carbon\Carbon;

class OAuthToken
{
	private $access_token;
	private $refresh_token;
	private $scope;
	private $expired_at;
	private $created_at;
	public $client_id;
	private $isFresh;


	public function __construct(string $client_id)
	{
		$this->client_id = $client_id;
	}


	/**
	 * @param array $token
	 * @return OAuthToken
	 */
	public function hydrate(array $token)
	{
		$this->access_token = empty($token['access_token']) ? '' : $token['access_token'];
		$this->refresh_token = empty($token['refresh_token']) ? '' : $token['refresh_token'];
		$this->scope = empty($token['scope']) ? '' : $token['scope'];

		$this->created_at = empty($token['created_at']) ? Carbon::now() : Carbon::createFromTimestamp($token['created_at']);

		if (!empty($token['expires_in'])) {
			$this->expired_at = $this->created_at->copy()->addSeconds(empty($token['expires_in']) ? 0 : $token['expires_in']);
			$this->isFresh = true;
		} else {
			$this->expired_at = Carbon::createFromTimestamp($token['expired_at']);
			$this->isFresh = false;
		}

		return $this;
	}


	/**
	 * @return mixed
	 */
	public function getAccessToken()
	{
		return $this->access_token;
	}


	/**
	 * @return mixed
	 */
	public function getRefreshToken()
	{
		return $this->refresh_token;
	}


	/**
	 * @return mixed
	 */
	public function getScope()
	{
		return $this->scope;
	}


	/**
	 * @return Carbon
	 */
	public function getExpiresIn()
	{
		return $this->expired_at->diffInSeconds(null, true);
	}


	public function getExpireDate()
	{
		return $this->expired_at;
	}


	public function isExpired()
	{
		return $this->getExpireDate()->isPast();
	}


	public function toJson()
	{
		return \GuzzleHttp\json_encode([
			'access_token'  => $this->access_token,
			'refresh_token' => $this->refresh_token,
			'scope'         => $this->scope,
			'expired_at'    => $this->expired_at->timestamp,
			'created_at'    => $this->created_at->timestamp,
		]);
	}


	/**
	 * @return mixed
	 */
	public function getIsFresh()
	{
		return $this->isFresh;
	}

}