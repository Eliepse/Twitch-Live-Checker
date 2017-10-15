<?php
/**
 * Created by PhpStorm.
 * User: margu
 * Date: 15/10/2017
 * Time: 09:34
 */

namespace App;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Predis\Response\ResponseInterface;

trait OAuthCredentialsRequestTrait
{
	protected function getRequestHeaders(OAuthToken $token): array
	{
		return [
			'Authorisation' => 'OAuth ' . $this->token->getAccessToken(),
			'Client-ID'     => $this->token->client_id,
		];
	}


	protected function requestBuilder(string $type, string $url, $parameters = null, OAuthToken $token): Request
	{

		switch ($type) {
			case 'GET' && is_array($parameters):
				$url .= $this->arrayToRequestUrl($parameters);
				break;

			default:
				$request_str = '';
		}

//		'https://api.twitch.tv/helix/users'

		$r = new Request('GET', $url, $this->getRequestHeaders($token));

		if ($type === 'POST')
			$r->postArray = $parameters;

		return $r;
	}


	protected function arrayToRequestUrl(array $params): string
	{
		$strs = [];

		foreach ($params as $key => $value) {

			if (is_array($value))
				$value = $this->joinRequestArrayValue($key, $value);
			else
				$value = rawurlencode($value);

			$strs[] = rawurlencode(trim($key)) . '=' . trim($value);

		}

		return '?' . join('&', $strs);

	}


	protected function joinRequestArrayValue(string $key, array $values): string
	{
		$vals = [];

		foreach ($values as $val)
			$vals[] = rawurlencode($val);

		return join(',', $vals);
	}


	/**
	 * @param Request $request
	 * @param array|null $postArray
	 * @return ResponseInterface
	 */
	protected function sendRequest(Request $request, array $postArray = null): Response
	{
		$client = new Client();

		if (is_array($postArray)) {

			$response = $client->send($request, [
				'form_params' => $postArray,
			]);

		} else if (!empty($request->postArray)) {

			$response = $client->send($request, [
				'form_params' => $request->postArray,
			]);

		} else {

			$response = $client->send($request);

		}

		return $response;

	}

}