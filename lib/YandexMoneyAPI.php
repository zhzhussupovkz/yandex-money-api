<?php

/**
* YandexMondeyAPI class
* @author zhzhussupovkz@gmail.com
*/

class YandexMondeyAPI {

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
	private function get_code($scope) {
		$auth_params = array(
			'client_id' => $this->oauth_params['client_id'],
			'response_type' => 'code',
			'redirect_uri' => $this->oauth_params['redirect_uri'],
			'scope' => $scope,
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
			throw new Exception($this->get_error('access_denied'));
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
	private function get_auth_token($scope) {
		$code = $this->get_code($scope);
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
			throw new Exception("Error: ".$this->get_error($final['error']));
		return $final['access_token'];
	}

	/*
	Authorization
	*/
	public function authorization($scope = 'account-info') {
		$this->bearer_token = $this->get_auth_token($scope);
	}

	//send request
	private function send_request($method, $params = array()) {
		$header = array(
			'POST /api/'.$method.' HTTP/1.1',
			'Host: money.yandex.ru',
			'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
			'Authorization: Bearer '.$this->bearer_token,
		);
		$ch = curl_init();
		$options = array(
			CURLOPT_URL => $this->api_url.$method,
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
			throw new Exception("Error: ".$this->get_error($final['error']));
		return $final;
	}

	//return messages by error codes
	private function get_error($code = 'invalid_request') {
		$errors = array(
			'invalid_request' => 'Формат HTTP запроса не соответствует протоколу.',
			'invalid_token' => 'Указан несуществующий, просроченный, или отозванный токен.',
			'insufficient_scope' => 'Запрошена операция, на которую у токена нет прав.',
			'unauthorized_client' => 'Неверное значение параметра client_id или client_secret, либо приложение не имеет права запрашивать авторизацию',
			'invalid_grant' => 'В выдаче access_token отказано',
			'access_denied' => 'Пользователь отклонил запрос авторизации приложения.',
			'illegal_param_type' => 'Неверное значение параметра type.',
			'illegal_param_start_record' => 'Неверное значение параметра start_record.',
			'illegal_param_records' => 'Неверное значение параметра records.',
			'illegal_param_label' => 'Неверное значение параметра label.',
			'illegal_param_from' => 'Неверное значение параметра from.',
			'illegal_param_till' => 'Неверное значение параметра till.',
			'illegal_param_operation_id' => 'Неверное значение параметра operation_id.',
			);
		if (!array_key_exists($code, $errors))
			return 'Техническая ошибка, повторите вызов операции позднее.';
		return $this->errors[$code];
	}

	/*********************** Получение информации о счете пользователя *************8/

	/*
	account_info - Получение информации о состоянии счета пользователя. 
	*/
	public function account_info() {
		return $this->send_request('account-info');
	}

	/*
	operation_history - Метод позволяет просматривать историю операций 
	(полностью или частично) в постраничном режиме. 
	Записи истории выдаются в обратном хронологическом 
	порядке: от последних к более ранним. 
	*/
	public function operation_history($params = array()) {
		return $this->send_request('operation-history', $params);
	}

	/*
	operation_details - Позволяет получить детальную информацию об операции из истории.
	*/
	public function operation_details($operation_id = null) {
		if (!$operation_id)
			throw new Exception($this->get_error('illegal_param_operation_id'));
		$params = array('operation_id' => $operation_id);
		return $this->send_request('operation-details', $params);
	}

}