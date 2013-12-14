<?php

/**
* Auth class
* @author zhzhussupovkz@gmail.com
*/

class Auth {

	//api_url
	private $api_url = 'https://money.yandex.ru/api/';

	//oauth params
	private $oauth_params;

	//bearer token
	private $bearer_token;

	public function __construct($client_id = null, $client_secret = null, $redirect_uri = null) {
		$this->oauth_params = array(
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'redirect_uri' => $redirect_uri,
			);
	}

	//get code for authorization
	private function get_code() {
		$auth_params = array(
			'client_id' => $this->oauth_params['client_id'],
			'response_type' => 'code',
			'redirect_uri' => $this->oauth_params['redirect_uri'],
			'scope' => 'account-info operation-history',
			);

		$header = array(
			'POST /oauth/authorize HTTP/1.1',
			'Host: sp-money.yandex.ru',
			'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
			'Content-Length: '.strlen(http_build_query($auth_params)),
		);

		$ch = curl_init();
		$options = array(
			CURLOPT_URL => $this->api_url.'oauth/authorize',
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => 0,
			);
		curl_setopt_array($ch, $options);
		$response = curl_exec($ch);
		if ($response == false)
			throw new Exception('Error: '.curl_error($ch));
		curl_close($ch);
		if (!$response)
			throw new Exception('Server response invalid data type');
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = $this->get_headers($response);
		if ($header['http_code'] != '302')
			throw new Exception("Server response is not valid");
		header("Location: ".$header['location']);
		if (!isset($_GET['code']))
			throw new Exception("Access Denied");
		return $_GET['code'];
	}

	//get headers from server response
	private function get_headers($response) {
		$headers = array();
		$header_text = substr($response, 0, strpos($response, "\r\n\r\n"));
		foreach (explode("\r\n", $header_text) as $i => $line) {
			if ($i === 0)
				$headers['http_code'] = $line;
			else {
				list ($key, $value) = explode(': ', $line);
				$headers[$key] = $value;
			}
		}
		return $headers;
	}

	//get auth token
	private function get_auth_token() {
		$code = $this->get_code();
		$auth_params = array(
			'code' => $code,
			'client_id' => $this->oauth_params['client_id'],
			'grant_type' => 'authorization_code',
			'redirect_uri' => $this->oauth_params['redirect_uri'],
			'client_secret' => $this->oauth_params['client_secret'],
			);

		$header = array(
			'POST /oauth/token HTTP/1.1',
			'Host: sp-money.yandex.ru',
			'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
			'Content-Length: '.strlen(http_build_query($auth_params)),
		);

		$ch = curl_init();
		$options = array(
			CURLOPT_URL => $this->api_url.'oauth/token',
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => 0,
			);
		curl_setopt_array($ch, $options);
		$response = curl_exec($ch);
		if ($response == false)
			throw new Exception('Error: '.curl_error($ch));
		curl_close($ch);
		if (!$response)
			throw new Exception('Server response invalid data type');
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = $this->get_headers($response);
		if ($header['http_code'] != '200')
			throw new Exception("Server response is not 200 status");
		$final = json_decode($response, TRUE);
		if (!$final)
			throw new Exception('Server response data format is not valid');
		if (isset($final['error']))
			throw new Exception("Error: ".$final['error']);
		return $final['access_token'];
	}

	/*
	Authorization
	*/
	public function authorization() {
		$this->bearer_token = $this->get_auth_token();
	}

}