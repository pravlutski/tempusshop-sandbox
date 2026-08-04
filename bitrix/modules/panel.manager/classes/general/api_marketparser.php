<?php
/**
 * api https://cp.marketparser.ru/docs/api#http-%D0%BC%D0%B5%D1%82%D0%BE%D0%B4%D1%8B
 */ 
class MParserAPI
{
	private $apiKey = "NzQ5ODBhYWVjOGI1YjM0NzdjZGM2ZDM2NmM5ZDZmMGYzY2I3MjczOA";
	private $api_url = "https://cp.marketparser.ru/api/v2/";

	//Список компаний
	public function getCompanyList(){
		$process = curl_init($this->api_url . "campaigns.json");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Api-Key: " . $this->apiKey, 
				"Content-Type: application/json"
			)
		);
		//curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		//curl_setopt($process, CURLOPT_POSTFIELDS, $_data);
		$result = curl_exec($process);
		$result = json_decode($result, true);
		return $result;

	}

	//Получение списка отчётов кампании
	public function getReportListCompany($compaign_id){
		$compaign_id = intval($compaign_id);
		if($compaign_id <= 0) return false;
		$process = curl_init($this->api_url . "campaigns/{$compaign_id}/reports.json");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Api-Key: " . $this->apiKey, 
				"Content-Type: application/json"
			)
		);
		//curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		//curl_setopt($process, CURLOPT_POSTFIELDS, $_data);
		$result = curl_exec($process);
		$result = json_decode($result, true);
		return $result;
	}
	
	//Получение результатов парсинга отчёта
	public function getParseResult($compaign_id, $report_id, $page){
		$compaign_id = intval($compaign_id);
		$report_id = intval($report_id);
		$page = intval($page);
		if($page == 0) $page = 1;
		if($compaign_id <= 0 || $report_id <= 0) return false;
		$process = curl_init($this->api_url . "campaigns/{$compaign_id}/reports/{$report_id}/results.json?per_page=100&page={$page}");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Api-Key: " . $this->apiKey, 
				"Content-Type: application/json"
			)
		);
		//curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		//curl_setopt($process, CURLOPT_POSTFIELDS, $_data);
		$result = curl_exec($process);
		$result = json_decode($result, true);
		return $result;
	}
	
	//Обновление прайса кампании
	public function setPriceCompany($compaign_id, $priceData){
		//$priceData['products'] = array_slice($priceData['products'], 0, 1);  
		$compaign_id = intval($compaign_id);
		if($compaign_id <= 0) return false;
		$process = curl_init($this->api_url . "campaigns/{$compaign_id}/price.json");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Api-Key: " . $this->apiKey, 
				"Content-Type: application/json"
			)
		);

		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POST, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($priceData));
		curl_setopt($process, CURLOPT_SSL_VERIFYPEER, TRUE);
		curl_setopt($process, CURLOPT_SSL_VERIFYHOST, 2);
		//you can download cacert.pem here: https://curl.haxx.se/ca/cacert.pem
		curl_setopt($process, CURLOPT_CAINFO, $_SERVER["DOCUMENT_ROOT"] . "/upload/cacert.pem");
		$result = curl_exec($process);
		$curlError = curl_error($process);
		file_put_contents("/home/bitrix/logs/yaparser.txt", print_r([$result, $priceData], true));
		$result = json_decode($result, true);
		return $result;
	}
	
	//Получение информации о прайсе кампании
	public function getPriceInfo($compaign_id){
		$compaign_id = intval($compaign_id);
		if($compaign_id <= 0) return false;
		$process = curl_init($this->api_url . "campaigns/{$compaign_id}/price.json");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Api-Key: " . $this->apiKey, 
				"Content-Type: application/json"
			)
		);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		$result = curl_exec($process);
		$result = json_decode($result, true);
		return $result;
	}
	//Создание отчёта по кампании
	public function setReportCompany($compaign_id){
		$compaign_id = intval($compaign_id);
		if($compaign_id <= 0) return false;
		$process = curl_init($this->api_url . "campaigns/{$compaign_id}/reports.json");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Api-Key: " . $this->apiKey, 
				"Content-Type: application/json"
			)
		);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'POST');
		//curl_setopt($process, CURLOPT_POST, TRUE);
		//curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($compaign_id));
		$result = curl_exec($process);
		
		$result = json_decode($result, true);
		return $result;
	}
	/**
     * Конструктор инициализирует CURL и необходимые параметры
     *
     * @param <type> $_client_login
     * @param <type> $_client_password
     */
/*    public function  __construct()
    {
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
*/
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
}