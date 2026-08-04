<?php
/**
 * api allegro class
 */ 
class Allegro_API
{

	public $default_comment = "Магазин в центре Минска. Более 10 000 товаров в каталоге. 5 лет работаем для Вас!";
	private $access_token;
    private $api_url_auth = "https://allegro.pl/auth/oauth/token";
    private $https = FALSE; //API работает через https
	
	private $client_id = "57cbfca6b43044dda3257f36aab60617";
	private $client_secret = "F4atqVwHbjUQYTfK6zUPu0x82fgwr4rH8D4SwAC47W5VNoNJD8TVOpmLu5RUyMmP";
	private $redirect_uri = "https://tempusshop.pl/auth/allegro/";
	/**
     * Конструктор инициализирует CURL и необходимые параметры
     *
     * @param <type> $_client_login
     * @param <type> $_client_password
     */
    public function  __construct()
    {
        //инициализируем CURL
        $this->curl = curl_init();

		curl_setopt($this->curl, CURLOPT_URL, $this->api_url_auth);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Accept: application/json'));
		curl_setopt($this->curl, CURLOPT_USERPWD, "{$this->client_id}:{$this->client_secret}");
		curl_setopt($this->curl, CURLOPT_POST, 1);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, array('grant_type' => 'client_credentials'));

		$result = curl_exec($this->curl);

		$obj_access = json_decode($result);
		$access = $obj_access->{'access_token'};
		//при успешной авторизации получаем код доступа сессии
		$this->access_token = $access;
		//prent($result);die;
	}

    /**
     * функция для пакетной загрузки данных
     * принимает массив, в котором надо передавать все редактируемые позиции
     *
     */

    public function edit_position_pack($_data = array()){
		$access = $this->access_token;
		$process = curl_init("https://b2bapi.onliner.by/pricelists");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Accept: application/json", 
				"Content-Type: application/json", 
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);
		$result = curl_exec($process);
		return $result;
	}
	
	
	public function setOffers($_data = array()){
		$access = $this->access_token;
		$process = curl_init("https://api.allegro.pl/sale/offers");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				//"Accept: application/json", 
				//"Content-Type: application/json", 
				"Authorization: Bearer $access",
				"content-type: application/vnd.allegro.public.v1+json",
				"accept: application/vnd.allegro.public.v1+json"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);
		$result = curl_exec($process);
		return $result;
	}
	public function getCategories($parent_id = false){
		$access = $this->access_token;
		//$process = curl_init("https://api.allegro.pl/sale/categories/{$parent_id}");
		$process = curl_init("https://api.allegro.pl/sale/categories?parent.id={$parent_id}");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Accept: application/json", 
				"Content-Type: application/json", 
				"Authorization: Bearer $access",
				"Accept: application/vnd.allegro.public.v1+json"
			)
		);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		$result = curl_exec($process);
		return $result;
	}
}