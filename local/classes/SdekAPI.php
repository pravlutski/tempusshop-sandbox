<?
//if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader;

class SdekAPI {
    //private $clientId = 'UGxmzLDWhrvHF8tpIH5ERCzRJzJNDpQP';
    //private $clientSecret = 'LTTOrp1f3XI29NusT4Ta3w1JfUNtutFq';
    private $clientId = 'WHl8zD3uVdHcW22bys0Ng2owatLQFKn3';
    private $clientSecret = 'hZGmFuhchHEAIOFh9VSmHlVf4yFUgbAW';
    private $accessToken;
    private $tokenExpiresAt;
    private $apiUrl = 'https://api.cdek.ru/v2';

	public function __construct(){
		$this->loadModules();
		$token = $this->getAccessToken();
		if ($token) $this->accessToken = $token;
	}
	
	private function loadModules(){
		//Loader::includeModule("main");
		Loader::includeModule("panel.manager");
		//if (!Loader::includeModule("intaro.retailcrm")) die('not installed intaro.retailcrm');
		global $DB;
		$this->db = $DB;
	}

    public function getAccessToken()
    {
        if ($this->accessToken && $this->tokenExpiresAt > time()) {
            return $this->accessToken;
        }
        
        $url = $this->apiUrl . '/oauth/token';
        
        $params = [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret
        ];
        
        $postData = http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Ошибка cURL: " . $error);
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $errorMessage = isset($result['error_description']) 
                ? $result['error_description'] 
                : 'Неизвестная ошибка';
            throw new Exception("Ошибка авторизации: " . $errorMessage);
        }
        
        if (!isset($result['access_token'])) {
            throw new Exception("Токен не получен в ответе");
        }
        
        $this->accessToken = $result['access_token'];
        $this->tokenExpiresAt = time() + $result['expires_in'] - 60;
        
        return $this->accessToken;
    }
	
    public function send($action, $data = [], $method = "GET", $headers = []){
        //$token = $this->getAccessToken(); не делаю переавторизацию.
        
		if (!$this->accessToken) {
			throw new Exception("Invalid token");
		}
		
        $url = $this->apiUrl . $action;
        
        $ch = curl_init();
        
        $requestHeaders = array_merge([
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ], $headers);
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
                
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
                
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
                
            case 'GET':
            default:
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Ошибка cURL: " . $error);
        }
        
		if (in_array('Accept: application/octet-stream', $requestHeaders))
			return $response;
		
        $result = json_decode($response, true);
        
        /*if ($httpCode === 401) {
            $this->accessToken = null;
            return $this->makeRequest($method, $action, $data, $headers);
        }*/

        if ($httpCode >= 400) {
            $errorMessage = isset($result['error_description']) 
                ? $result['error_description'] 
                : (isset($result['error']) ? $result['error'] : 'Неизвестная ошибка');
            throw new Exception("Ошибка API ({$httpCode}): " . $errorMessage);
        }
        
        return $result;
    }

}
