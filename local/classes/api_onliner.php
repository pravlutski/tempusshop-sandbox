<?php
/**
 * api onliner class
 */ 
class Onliner_API
{
	public $default_comment = "";//'Магазин в центре Минска. Более 15 000 моделей в каталоге. 6 лет работаем для Вас! Возможна оплата банковской картой. Карты рассрочек "Халва" и "Карта покупок"';
	private $access_token;
    private $api_url_auth = "https://b2bapi.onliner.by/oauth/token";
    private $https = FALSE; //API работает через https
	
	private $client_id = "";//"9609d7ca99cff2632f1b";
	private $client_secret = "";//"669453e70e6e274d8ac1c8c5344bf7518e7463de";
	/**
     * Конструктор инициализирует CURL и необходимые параметры
     *
     * @param <type> $_client_login
     * @param <type> $_client_password
     */
    public function  __construct()
    {
		$this->default_comment = COption::GetOptionString("panel.manager", "ONLINER_DEFAULT_TEXT");
		$this->client_id = COption::GetOptionString("panel.manager", "ONLINER_CLIENT_ID");
		$this->client_secret = COption::GetOptionString("panel.manager", "ONLINER_CLIENT_SECRET");
		
		$_client_id = $this->client_id;
		$_client_secret = $this->client_secret;
        //инициализируем CURL
        $this->curl = curl_init();

		curl_setopt($this->curl, CURLOPT_URL, $this->api_url_auth);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Accept: application/json'));
		curl_setopt($this->curl, CURLOPT_USERPWD, "{$_client_id}:{$_client_secret}");
		curl_setopt($this->curl, CURLOPT_POST, 1);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, array('grant_type' => 'client_credentials'));

		$result = curl_exec($this->curl);

		$obj_access = json_decode($result);
		$access = $obj_access->{'access_token'};
		//при успешной авторизации получаем код доступа сессии
		$this->access_token = $access;

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
	//Отчет по импорту
	public function report_pricelist($pricelist_id){
		$access = $this->access_token;
		$process = curl_init("https://b2bapi.onliner.by/pricelists/{$pricelist_id}/report");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Accept: application/json", 
				"Content-Type: application/json", 
				"Authorization: Bearer $access"
			)
		);
		//curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		//curl_setopt($process, CURLOPT_POSTFIELDS, $_data);
		$result = curl_exec($process);
		return $result;
	}
	public function status_pricelist($pricelist_id){
		$access = $this->access_token;
		$process = curl_init("https://b2bapi.onliner.by/pricelists/{$pricelist_id}/status");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Accept: application/json", 
				"Content-Type: application/json", 
				"Authorization: Bearer $access"
			)
		);
		//curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		//curl_setopt($process, CURLOPT_POSTFIELDS, $_data);
		$result = curl_exec($process);
		return $result;
	}
}