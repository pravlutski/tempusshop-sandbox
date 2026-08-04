<?
/*
Api yandex
*/
use Bitrix\Main\Loader;
class YandexAPI{
	private $api_url;
	public function __construct($setupId){
		$this->loadModules();
		
		$this->setupId = $setupId;
		
		$arSettings = $this->getSetting();
		
		$this->api_url = 'https://api.partner.market.yandex.ru';
        $this->apiKey = $arSettings["API_KEY"];
		$this->businessId = $arSettings["BUSINESS_ID"];
		$this->compaignId = $arSettings["CAMPAIGN_ID"];
		
		$this->logger = new TsLogger("/" . __CLASS__ . "/");
	}
	
	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("panel.manager");
		global $DB;
		$this->db = $DB;
	}
	
	private function getSetting(){
		
		$strSql = "SELECT * FROM yamarket_trading_settings WHERE NAME = 'API_KEY'";

		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		$arSetting = [];
		if ($row = $results->Fetch()) {
			$arSetting['API_KEY'] = $row['VALUE'];
		}
		
		$strSql = "SELECT * FROM yamarket_trading_setup WHERE ID = '{$this->setupId}'";

		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);

		if ($row = $results->Fetch()) {
			$arSetting['BUSINESS_ID'] = $row['BUSINESS_ID'];
			$arSetting['CAMPAIGN_ID'] = $row['CAMPAIGN_ID'];
		}
		
		return $arSetting;
	}
	
	// Получить файл с этикетками
	public function getStickerFile($orderIds, $format = 'A9_HORIZONTALLY') {
		if (is_string($orderIds)) $orderIds = [$orderIds];
		
		$data = array(
			"businessId" => $this->businessId,
			"orderIds" => $orderIds,
		);
		$this->logger->log("LOG", "Получаем стикеры", $data);
		
		$res = $this->send(action: "/v2/reports/documents/labels/generate?format={$format}", method: "POST", data: $data);

		if($res["status"] == 'OK' && is_array($res["result"])){
			return $res["result"];
		} else {
			$this->logger->log("ERROR", "Ошибка получения стикеров", $res);
		}

		return false;
	}
	
	public function getReportResult($reportId = '') {
		if (!$reportId) return false;

		$this->logger->log("LOG", "Получаем результат отчета", $data);
		
		$res = $this->send(action: "/v2/reports/info/{$reportId}", method: "GET");

		if($res["status"] == 'OK' && is_array($res["result"])){
			return $res["result"];
		} else {
			$this->logger->log("ERROR", "Ошибка получения результат отчета", $res);
		}

		return false;
	}
	
    public function send($action, $data = [], $method = "GET", $header = []){
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
    // Проверяем разные возможные места хранения заголовков
    if (isset($response->headers) && is_object($response->headers)) {
        // Если headers - объект, проверяем свойство Content-Type
        if (isset($response->headers->{'Content-Type'})) {
            return $response->headers->{'Content-Type'};
        }
    }
    
    if (isset($response->headers) && is_array($response->headers)) {
        // Если headers - массив
        if (isset($response->headers['Content-Type'])) {
            return $response->headers['Content-Type'];
        }
    }
    
    // Проверяем info массив
    if (isset($response->info) && is_array($response->info)) {
        if (isset($response->info['content_type'])) {
            return $response->info['content_type'];
        }
    }
    
    // Альтернативный способ - ищем в сырых заголовках
    if (isset($response->headers) && is_string($response->headers)) {
        if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $response->headers, $matches)) {
            return trim($matches[1]);
        }
    }
    
    return null;
}

/**
 * Парсит заголовки ответа
 */
private function parseHeaders($response)
{
    $headers = [];
    
    if (isset($response->headers)) {
        if (is_object($response->headers)) {
            // Конвертируем объект в массив
            $headers = json_decode(json_encode($response->headers), true);
        } elseif (is_array($response->headers)) {
            $headers = $response->headers;
        }
    }
    
    return $headers;
}

/**
 * Проверяет, является ли ответ файлом
 */
private function isFileResponse($response)
{
    // Проверяем Content-Type из заголовков
    $contentType = $this->getContentType($response);
    
    if ($contentType) {
        // Если Content-Type указывает на файл (PDF, изображение, и т.д.)
        if (preg_match('/(pdf|image|octet-stream|excel|word|zip)/i', $contentType)) {
            return true;
        }
    }
    
    // Дополнительная проверка по содержимому (первые несколько байт)
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