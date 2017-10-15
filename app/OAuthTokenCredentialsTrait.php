<?php
/**
 * Created by PhpStorm.
 * User: margu
 * Date: 15/10/2017
 * Time: 09:28
 */

namespace App;

use GuzzleHttp\Client as HttpClient;

trait OAuthTokenCredentialsTrait
{

	protected $token_request_url = '';
	protected $app_client_id = '';
	protected $app_client_secret = '';
	protected $token;


	protected function getToken(): OAuthToken
	{
		if (!$this->isTokenValid()) {

			if (!$this->fetchNewToken())
				return null;

		}


		return $this->token;

	}


	protected function cacheToken(OAuthToken $token)
	{
		file_put_contents(storage_path('app/token.json'), $token->toJson());
	}


	protected function getTokenRequestParameters()
	{
		return [
			'client_id'     => $this->app_client_id,
			'client_secret' => $this->app_client_secret,
			'grant_type'    => 'client_credentials',
		];
	}


	protected function fetchNewToken(): bool
	{
		$client = new HttpClient();

		$response = $client->post($this->token_request_url, [
			'form_params' => $this->getTokenRequestParameters(),
		]);

		if ($response->getStatusCode() !== 200)
			return false;

		$raw_token = $response->getBody()->getContents();

		$token = new OAuthToken($this->app_client_id);
		$array_token = \GuzzleHttp\json_decode($raw_token, true);

		$token->hydrate($array_token);

		$this->cacheToken($token);
		$this->token = $token;

		return true;

	}


	/**
	 * @return OAuthToken|bool
	 */
	private function fetchCacheToken()
	{
		if (!file_exists(storage_path('app/token.json')))
			return false;

		$raw_token = file_get_contents(storage_path('app/token.json'));

		if (empty(trim($raw_token)))
			return false;

		$this->token = (new OAuthToken($this->app_client_id))->hydrate(json_decode($raw_token, true));

		return $this->token;
	}


	private function isTokenValid()
	{
		if (empty($this->token)) {

			if (!$this->fetchCacheToken())
				return false;

		}

		return !$this->token->isExpired();

	}

}