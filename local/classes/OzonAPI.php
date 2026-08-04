<?
/*
Api OZON
*/
use Bitrix\Main\Loader;
class OzonAPI{
	private $api_url;
	public function __construct($cabinet = "IP"){
		$this->loadModules();
		
		/*$this->api_url = "https://api-seller.ozon.ru";
		
		$arOptions = CMaxyssOzon::getOptions("s1");
		$arSettings = $arOptions[key($arOptions)];
		$this->clientID = $arSettings['OZON_ID'];
        $this->apiKey = $arSettings['OZON_API_KEY'];*/
		
		$this->cabinet = $cabinet;
		
		$arSettings = $this->getSetting();
		
		$this->api_url = $this->api_url_base = $arSettings["api_url"]["value"];
		$this->clientID = $arSettings["client_id"]["value"];;
        $this->apiKey = $arSettings["key"]["value"];;
	}
	
	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("panel.manager");
		global $DB;
		$this->db = $DB;
	}
	private function getSetting(){
		$setting = [];
		$this->panelDB = new DBPanel();
		$result = $this->panelDB->query("SELECT * FROM ozon_main_settings_{$this->cabinet}");
		$res = $this->panelDB->fetchAll($result);
		foreach($res as $k => $v){
			$setting[$v["name"]] = $v;
		}
		return $setting;
	}
	
	public function changeApiUrl($url = ""){
		if(strlen($url) > 0){
			$this->api_url = $url;
		}else{
			$this->api_url = $this->api_url_base;
		}
	}
	
	// Список отправлений (FBO) по фильтру
	public function getOrdersFBO($arFilter = []){
		$arOrders = [];
		$i = 0;
		$offset = 0;
		$limit = 1000;
		while(true){
			$data = [
				"dir" => "desc",
				"filter" => $arFilter,
				"limit" => $limit,
				"offset" => $offset,
			];
			
			$order = $this->send(action: "/v2/posting/fbo/list", data: $data, method: "POST");
			
			if(is_array($order["result"]) && count($order["result"]) > 0){
				if(!$arOrders) 
					$arOrders = $order["result"];
				else
					$arOrders = array_merge($arOrders, $order["result"]);
				
				if(count($order) < $limit) break;
				
				$offset += $limit;
			}else{
				break;
			}
			$i++;
			if($i > 10) break;
		}
		
		return $arOrders;
	}
	
	// Список необработанных отправлений
	public function getOrdersUnfulfilled($arFilter = []){
		switch($arFilter["cutoff"]){
			case "all":
				$cutoff_from = date("Y-m-d\TH:i:s\Z", strtotime("-2 months"));
				$cutoff_to = date("Y-m-d\TH:i:s\Z", strtotime("+3 months"));
				break;
			case "today":
				$cutoff_from = date("Y-m-d\T21:00:00\Z", strtotime("-2 months"));
				$cutoff_to = date("Y-m-d\T21:00:00\Z", time());
				break;
			case "tomorrow":
				$cutoff_from = date("Y-m-d\T21:00:00\Z", time());
				$cutoff_to = date("Y-m-d\T21:00:00\Z", strtotime("+1 day"));
				break;
			case "later":
				$cutoff_from = date("Y-m-d\T21:00:00\Z", time());
				$cutoff_to = date("Y-m-d\TH:i:s\Z", strtotime("+3 months"));
				break;
			default:
				break;
		}

		if($arFilter["cutoff"]){
			$data["filter"]["warehouse_id"] = $arFilter["warehouse"];
		}
		
		$data = [
                "dir" => "asc",
                "filter"=>array(
                    "status" => $arFilter["status"],
                    "cutoff_from" => $cutoff_from,
                    "cutoff_to" => $cutoff_to,
                ),
                "limit" => 1000,
                "offset" => 0,
                /*"with"=>array(
                    "analytics_data" => true,
                    "barcodes" => true,
                    "product_exemplars" => true,
                    "translit" => false,
                    "financial_data" => true
                )*/
		];
		if($arFilter["warehouse"]){
			$data["filter"]["warehouse_id"] = $arFilter["warehouse"];
		}
		if($arFilter["delivery-method"]){
			$data["filter"]["delivery_method_id"] = $arFilter["delivery-method"];
		}
		
		$order = $this->send(action: "/v3/posting/fbs/unfulfilled/list", data: $data, method: "POST");

		$arOrder = [];
		if(is_array($order["result"]["postings"])){
			foreach($order["result"]["postings"] as $arItem){
				$arOrder[$arItem["posting_number"]] = [
					"ID" => $arItem["order_id"],
					"POSTING_NUMBER" => $arItem["posting_number"],
					"ORDER_NUMBER" => $arItem["order_number"],
					"PRICE" => $arItem["products"][0]["price"],
					"ARTICLE_OZON" => $arItem["products"][0]["offer_id"],
					"DELIVERY" => $arItem["delivery_method"],
					"SHIPMENT_DATE" => $arItem["shipment_date"],
				];
			}
		}
		return $arOrder;
	}
	
	// Получить информацию об отправлении по идентификатору
	public function getOrder(string $posting_number){
		$data = [
            "posting_number" => $posting_number,
		];
		
		return $this->send(action: "/v3/posting/fbs/get", data: $data, method: "POST");
	}

	// Получить информацию о цене товара
	public function getPrice(array $arFilter){
		$data = [
			"filter" => $arFilter,
			"limit" => 1000
		];
		//array("offer_id" => array_keys($arEl))
		return $this->send(action: "/v4/product/info/prices", data: $data, method: "POST");
	}
	
	// Собрать заказ (версия 4). Переводим отправление в статус "Ожидают отгрузки"
	public function setOrderCollect(array $data){
		return $this->send(action: "/v4/posting/fbs/ship", data: $data, method: "POST");
	}
	
	// Список складов
	public function getWarehouseList(){

		$res = $this->send(action: "/v1/warehouse/list", method: "POST");
		$warehouse = [];
		if(is_array($res["result"])){
			foreach($res["result"] as $arItem){
				if(!$arItem["status"] == "disabled") continue;
				$warehouse[$arItem["warehouse_id"]] = [
					"ID" => $arItem["warehouse_id"],
					"NAME" => $arItem["name"],
				];
			}
		}
		return $warehouse;
	}
	
	// Список методов доставки склада
	public function getDeliveryMethodList($arWarehouse = []){
		$data = array(
			"filter" => array(
				"status" => "ACTIVE",
				//"warehouse_id" => $warehouse["warehouse_id"],
			),
			"limit" => 50,
			"offset" => 0
		);
		$res = $this->send(action: "/v1/delivery-method/list", method: "POST", data: $data);
		$delivery = [];
		if(is_array($res["result"])){
			foreach($res["result"] as $arItem){
				$delivery[$arItem["id"]] = [
					"ID" => $arItem["id"],
					"NAME" => $arItem["name"] . ($arWarehouse[$arItem["warehouse_id"]] ? " (" . $arWarehouse[$arItem["warehouse_id"]]["NAME"] . ")" : ""),
				];
			}
		}
		return $delivery;
	}
	
	// Создать задание на выгрузку этикеток
	public function createTaskSticker(array $arOrderID){
		$data = array(
			"posting_number" => $arOrderID,
		);
		$res = $this->send(action: "/v1/posting/fbs/package-label/create", method: "POST", data: $data);
		file_put_contents("/var/www/bitrix_logs/ozon/createTaskSticker_all.txt", print_r([$arOrderID, $res], true), 8);
		if(is_array($res)){
			return $res;
			//return $res["result"]["task_id"];
		} else {
			file_put_contents("/var/www/bitrix_logs/ozon/error_createTaskSticker.txt", print_r([$arOrderID, $res], true), 8);
		}
		
		return false;
	}
	
	// Получить файл с этикетками
	public function getStickerFile(int $task_id){
		$data = array(
			"task_id" => $task_id,
		);
		$res = $this->send(action: "/v1/posting/fbs/package-label/get", method: "POST", data: $data);

		if(is_array($res["result"])){
			return $res["result"];
		}

		file_put_contents("/var/www/bitrix_logs/ozon/error_getStickerFile.txt", print_r([$task_id, $res], true), 8);
		return false;
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
	
	// Получить аналитику по остаткам
	public function getAnalyticsStocks($arFilter = []){
		$arOrders = [];
		$data = [];
		
		$report = $this->send(action: "/v1/analytics/stocks", data: $arFilter, method: "POST");
		//prent($report);
		/*$i = 0;
		$offset = 0;
		$limit = 1000;
		while(true){
			$data = [
			];
			
			$order = $this->send(action: "/v1/analytics/stocks", data: $data, method: "POST");
			
			if(is_array($order["result"]) && count($order["result"]) > 0){
				if(!$arOrders) 
					$arOrders = $order["result"];
				else
					$arOrders = array_merge($arOrders, $order["result"]);
				
				if(count($order) < $limit) break;
				
				$offset += $limit;
			}else{
				break;
			}
			$i++;
			if($i > 10) break;
		}*/
		
		return $report;
	}
	
	// Управление остатками https://docs.ozon.ru/api/seller/#operation/AnalyticsAPI_ManageStocks
	public function getAnalyticsManage($arFilter = []){
		$arStock = [];

		$data = [
			//"filter" => $arFilter,
			"offset" => 0,
			"limit" => 10,
		];
		
		$report = $this->send(action: "/v1/analytics/manage/stocks", data: $data, method: "POST");
		//prent($report);
		/*$i = 0;
		$offset = 0;
		$limit = 1000;
		while(true){
			$data = [
			];
			
			$order = $this->send(action: "/v1/analytics/stocks", data: $data, method: "POST");
			
			if(is_array($order["result"]) && count($order["result"]) > 0){
				if(!$arOrders) 
					$arOrders = $order["result"];
				else
					$arOrders = array_merge($arOrders, $order["result"]);
				
				if(count($order) < $limit) break;
				
				$offset += $limit;
			}else{
				break;
			}
			$i++;
			if($i > 10) break;
		}*/
		
		return $report;
	}
	
    public function send($action, $data = [], $method = "GET", $header = []){
		file_put_contents('/var/www/bitrix_logs/ozon/timing.txt', print_r(['OzonApi', date('Y-m-d H:i:s')], true), 8);
		if($data){
			$data_string = \Bitrix\Main\Web\Json::encode($data);
		}else{
			$data_string = "";
		}
		$header = array_merge([
			'Content-Type: application/json',
			"Client-Id: " . $this->clientID,
			"Api-Key: " . $this->apiKey,
			//'Content-Type: json',
			'Content-Length: ' . strlen($data_string)
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

		if ($isFile || $this->isFileResponse($str_result)) {
			return [
				'status' => $str_result->info->http_code,
				'content_type' => $this->getContentType($str_result),
				'data' => $str_result->response,
				'headers' => $this->parseHeaders($str_result)
			];
		}
	
        /*if ($str_result->info->http_code == 200 && strlen($str_result->response) > 0) {
            $res = \Bitrix\Main\Web\Json::decode($str_result->response);
        }else{
			$res = array('status' => $str_result->info->http_code, "error" => (is_array($str_result->response) ? \Bitrix\Main\Web\Json::decode($str_result->response) : $str_result->response));
		}*/
		if ($str_result->info->http_code == 200 && strlen($str_result->response) > 0) {
			try {
				$res = \Bitrix\Main\Web\Json::decode($str_result->response);
			} catch (\Exception $e) {
				$res = [
					'status' => $str_result->info->http_code,
					'error' => 'JSON decode error: ' . $e->getMessage(),
					'raw_response' => $str_result->response
				];
			}
		} else {
			$res = [
				'status' => $str_result->info->http_code,
				'error' => (is_array($str_result->response) ? \Bitrix\Main\Web\Json::decode($str_result->response) : $str_result->response)
			];
		}
	
        return $res;
    }
	
	private function getContentType($response)
	{
		if (isset($response->headers) && is_object($response->headers)) {
			if (isset($response->headers->{'Content-Type'})) {
				return $response->headers->{'Content-Type'};
			}
		}
		
		if (isset($response->headers) && is_array($response->headers)) {
			if (isset($response->headers['Content-Type'])) {
				return $response->headers['Content-Type'];
			}
		}
		
		if (isset($response->info) && is_array($response->info)) {
			if (isset($response->info['content_type'])) {
				return $response->info['content_type'];
			}
		}
		
		if (isset($response->headers) && is_string($response->headers)) {
			if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $response->headers, $matches)) {
				return trim($matches[1]);
			}
		}
		
		return null;
	}

	private function parseHeaders($response)
	{
		$headers = [];
		
		if (isset($response->headers)) {
			if (is_object($response->headers)) {
				$headers = json_decode(json_encode($response->headers), true);
			} elseif (is_array($response->headers)) {
				$headers = $response->headers;
			}
		}
		
		return $headers;
	}

	private function isFileResponse($response)
	{
		$contentType = $this->getContentType($response);
		
		if ($contentType) {
			if (preg_match('/(pdf|image|octet-stream|excel|word|zip)/i', $contentType)) {
				return true;
			}
		}
		
		if (strlen($response->response) > 0) {
			$content = substr($response->response, 0, 100);
			
			if (strpos($content, '%PDF-') === 0) { // PDF файл
				return true;
			}
			if (strpos($content, 'PK') === 0) { // ZIP/Office документы
				return true;
			}
			if (strpos($content, '\xFF\xD8\xFF') === 0) { // JPEG
				return true;
			}
		}
		
		return false;
	}
}