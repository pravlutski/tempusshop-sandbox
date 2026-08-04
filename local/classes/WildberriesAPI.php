<?
//if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader;
//if(!Loader::includeModule('maxyss.wb'))return;

//class WildberriesAPI extends CMaxyssWbSupplies{
class WildberriesAPI{
	private $api_url;
	public function __construct($cabinet = "WR"){
		$this->loadModules();
		//parent::__construct($cabinet);
		$this->api_url_base = $this->api_url = "https://content-api.wildberries.ru/content/v2";
		
		$this->cabinet = $cabinet;
		
		$arSettings = $this->getSetting();
		
		if(!$arSettings){
			die("no settings");
		}
		
        //$this->apiKey = $arSettings["api"];
		
		$this->apiKey = $arSettings["api"];
		
		/*$arApiKey = [
			$arSettings["api"],
			"eyJhbGciOiJFUzI1NiIsImtpZCI6IjIwMjQxMjE3djEiLCJ0eXAiOiJKV1QifQ.eyJlbnQiOjEsImV4cCI6MTc1MDQ2NzA4OSwiaWQiOiIwMTkzZTQxZi03ZWFjLTdiNjYtYmU0Zi1mMjNiMWE0Y2NlN2QiLCJpaWQiOjYxNTAwNjgsIm9pZCI6NzI0NjQ2LCJzIjo3OTM0LCJzaWQiOiJlNDNjODgyOS1jOWFkLTRjYzctYmVlZC01NDdmNGZmZTIzMmIiLCJ0IjpmYWxzZSwidWlkIjo2MTUwMDY4fQ.AneAt19tVK52sJ6FZrNhoJmnRSuf-fA2pQlKP1MSb6apt8WVvSk9EMdbljmnHCeTVA3-uRYrgxke_FfOz-tK4g",
			"eyJhbGciOiJFUzI1NiIsImtpZCI6IjIwMjQxMjE3djEiLCJ0eXAiOiJKV1QifQ.eyJlbnQiOjEsImV4cCI6MTc1MDQ2NzExOSwiaWQiOiIwMTkzZTQxZi1mNTUzLTc1ZjMtYTQ4NC03YTJjNWM2NjJiYTIiLCJpaWQiOjYxNTAwNjgsIm9pZCI6NzI0NjQ2LCJzIjo3OTM0LCJzaWQiOiJlNDNjODgyOS1jOWFkLTRjYzctYmVlZC01NDdmNGZmZTIzMmIiLCJ0IjpmYWxzZSwidWlkIjo2MTUwMDY4fQ.weUOTfl5rasfuYewfw8uuFbgCOgeMzfL4vCjBpty6MHFPWOw00gW-H9epTGO8HnE6Mx_JY9GtmFS7rZeYTHdjw"
		];
		//prent($arApiKey); 
		$file_last_num_key = "/home/bitrix/logs/wb/.wb_num_key_{$cabinet}";
		if(file_exists($file_last_num_key)){
			$last_num_key = intval(file_get_contents($file_last_num_key));
			$next_num_key = $last_num_key + 1;
			if ($arApiKey[$next_num_key]) {
				$this->apiKey = $arApiKey[$next_num_key];
			} else {
				$next_num_key = 0;
				$this->apiKey = $arApiKey[0];
			}
		}else{
			$next_num_key = 0;
			$this->apiKey = $arApiKey[0];
		}
		
		file_put_contents($file_last_num_key, $next_num_key);
		file_put_contents("/home/bitrix/logs/wb/api_key.txt", print_r(["date" => date("Y-m-d H:i:s"), "num_key" => $next_num_key, "apiKey" => $this->apiKey], true), 8);
		//$this->apiKey = $arSettings["api"];  
		global $USER;
		if($USER && $USER->getID() == 12677){  
		//	$this->apiKey = "eyJhbGciOiJFUzI1NiIsImtpZCI6IjIwMjQxMjE3djEiLCJ0eXAiOiJKV1QifQ.eyJlbnQiOjEsImV4cCI6MTc1MDQ2NzA4OSwiaWQiOiIwMTkzZTQxZi03ZWFjLTdiNjYtYmU0Zi1mMjNiMWE0Y2NlN2QiLCJpaWQiOjYxNTAwNjgsIm9pZCI6NzI0NjQ2LCJzIjo3OTM0LCJzaWQiOiJlNDNjODgyOS1jOWFkLTRjYzctYmVlZC01NDdmNGZmZTIzMmIiLCJ0IjpmYWxzZSwidWlkIjo2MTUwMDY4fQ.AneAt19tVK52sJ6FZrNhoJmnRSuf-fA2pQlKP1MSb6apt8WVvSk9EMdbljmnHCeTVA3-uRYrgxke_FfOz-tK4g";
		}*/
	}
	
	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("panel.manager");
		global $DB;
		$this->db = $DB;
		$this->panelDB = new DBPanel();
	}
	
	private function getSetting(){
		$strSql = "SELECT * FROM wdhs_wb_main_settings WHERE cabinet = '{$this->cabinet}'";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
	}
	
	public function getOrders(){
		$order = $this->send(action: "/api/v3/orders/new");
		$arOrder = [];
		if(is_array($order["orders"])){
			foreach($order["orders"] as $arItem){
				$arOrder[$arItem["id"]] = [
					"ID" => $arItem["id"],
					"PRICE" => $arItem["convertedPrice"] / 100,
					"ARTICLE_WB" => $arItem["article"],
					"NMID" => $arItem["nmId"],
				];
			}
		}
		return $arOrder;
	}

	// активные сборки
	public function getSuppliesActive(){
		$ar = [];
		$arSupplies = array_reverse($this->getSupplies());
		foreach($arSupplies as $arItem){
			if($arItem["done"] == false){
				$ar[] = $arItem;
			}
		}

		return $ar;
	}
	
	public function getSupplies() {
		$this->changeApiUrl("https://marketplace-api.wildberries.ru");
		
		$allSupplies = [];
		$next = 0;
		
		do {
			$res = $this->send(action: "/api/v3/supplies?limit=1000&next=" . $next);
			
			if (!empty($res['supplies'])) {
				$allSupplies = array_merge($allSupplies, $res['supplies']);
			}
			
			$next = $res['next'] ?? null;
			
		} while (!empty($res['supplies']) && $next !== null);
		
		$this->changeApiUrl();
		
		return $allSupplies;
	}
	
	// список заказов в поставке
	public function getSupplieItems($supplie_id = ""){
		if(!$supplie_id) return false;

		$order = $this->getSupplieOrders($supplie_id);

		$arOrder = [];
		if(is_array($order["orders"])){
			foreach($order["orders"] as $arItem){
				$arOrder[$arItem["id"]] = [
					"ID" => $arItem["id"],
					"PRICE" => $arItem["convertedPrice"] / 100,
					"ARTICLE_WB" => $arItem["article"],
					"NMID" => $arItem["nmId"],
				];
			}
		}
		return $arOrder;
	}

	// список заказов в доставке
	public function getOrdersFBO($dateFrom = "", $flag = 1){
		if(!$dateFrom) return false;

		$this->changeApiUrl("https://statistics-api.wildberries.ru/api/v1");

		$res = $this->send(action: "/supplier/orders?dateFrom={$dateFrom}&flag={$flag}");

		//prent($res,0,1);die;

		$this->changeApiUrl();

		return $res;

	}

	// Возвращает продажи и возвраты. Данные обновляются раз в 30 минут. 1 строка = 1 заказ = 1 единица товара. Для определения заказа рекомендуем использовать поле srid. Данные заказа хранятся 90 дней от даты продажи
	public function getSales($dateFrom = "", $flag = 1){
		if(!$dateFrom) return false;

		$this->changeApiUrl("https://statistics-api.wildberries.ru/api/v1");

		$res = $this->send(action: "/supplier/sales?dateFrom={$dateFrom}&flag={$flag}");

		//prent($res,0,1);die;

		$this->changeApiUrl();

		return $res;

	}
	
	// меняем статус заказа
	public function orderToSupplie ($supplyId, $orderIds){
		if(!$supplyId || !$orderIds) return false;

		$this->changeApiUrl("https://marketplace-api.wildberries.ru");
		
		// потом убрать эту хрень. $orderIds должен сразу правильным быть
		if (is_array($orderIds)) {
			$ar = [];
			foreach ($orderIds as $k => $v) {
				$ar[] = intval($v);
			}
			$data = [
				'orders' => $ar
			];
		} else {
			$data = [
				'orders' => [intval($orderIds)]
			];
		}
		//file_put_contents("/var/www/bitrix_logs/utils/set_status_order/ssssssssss.txt", print_r(
		//	[date('Y-m-d H:i:s'), $data, $orderIds], true), 8
		//);
		$res = $this->send(action: "/api/marketplace/v3/supplies/{$supplyId}/orders", data: $data, method: 'PATCH');

		$this->changeApiUrl();

		return $res;

	}
	
	public function createSupplie ($name) {
		if(!$name) return false;
		
		$this->changeApiUrl("https://marketplace-api.wildberries.ru");
		
		$data = [
			'name' => $name
		];
		$res = $this->send(action: "/api/v3/supplies", data: $data, method: 'POST');

		$this->changeApiUrl();

		return $res;

	}
	
	
	public function test(){
		$this->changeApiUrl("https://marketplace.wildberries.ru/ns/marketplace-app/marketplace-remote-wh/api/v3");

		$res = $this->send(action: "/portal/orders/new?next=0&order=desc&storeId=&type=all", data: [], method: "GET");

		//prent($res,0,1);die;

		$this->changeApiUrl();

		return $res;

	}
	public function changeApiUrl($url = ""){
		if(strlen($url) > 0){
			$this->api_url = $url;
		}else{
			$this->api_url = $this->api_url_base;
		}
	}

    public function send($action, $data = [], $method = "GET", $header = []){
		$data_string = \Bitrix\Main\Web\Json::encode($data);

		$header = array_merge([
			'Content-Type: application/json',
			'Authorization: ' . $this->apiKey,
		], $header);

		$api = new RestClient([
            'base_url' => $this->api_url,
            'curl_options' => array(
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_POSTFIELDS => $data_string,
                CURLOPT_HEADER => TRUE,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $header
            )
        ]);

        $str_result = $api->post(url: $action);

        if (($str_result->info->http_code == 200 || $str_result->info->http_code == 201) && strlen($str_result->response) > 0) {
            $res = \Bitrix\Main\Web\Json::decode($str_result->response); 
        }else{
			$res = array('status' => $str_result->info->http_code, "error" => ($str_result->response ? \Bitrix\Main\Web\Json::decode($str_result->response) : false));
		}

        return $res;
    }

}
