<?
//if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader;

class RetailCrmAPI {
	private $api;
	private $options;
	public function __construct(){
		$this->loadModules();
		
		$this->options = RetailcrmConfigProvider::getSitesList()[SITE_ID];
		$this->api = new RetailCrm\ApiClient(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());
		
		prent($this->api);
		if ($optionsSitesList[SITE_ID]) {
			$this->options = $optionsSitesList[SITE_ID];
			
			//file_put_contents("/var/www/bitrix/data/www/tempus.ru/local/classes/test.txt", print_r([$arOrder, $res], true), 8);
		}
	}
	
	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("panel.manager");
		if (!Loader::includeModule("intaro.retailcrm")) die('not installed intaro.retailcrm');
		global $DB;
		$this->db = $DB;
	}
	
	public function test(){
		//$asd = new RetailCrm\ApiClient ("http://tempusshop.retailcrm.ru", "");
		//$res = RCrmActions::apiMethod($this->api, 'sitesList', '/delivery/shipments', [], $this->options);

		
		$filter = [];
        if (count($filter)) {
            $parameters['filter'] = $filter;
        }
        if (null !== $page) {
            $parameters['page'] = $page;
        }
        if (null !== $limit) {
            $parameters['limit'] = $limit;
        }
        $parameters = [
			'deliveryShipment'
		];
		/*$res = $this->api->client->makeRequest(
            '/delivery/shipments/3/edit',
            'POST',
            $parameters
        ); */
		$parameters = [
			
		];
		$res = $this->api->client->makeRequest(
            '/integration-modules/delivery',
            'POST',
            $parameters
        ); 
		///api/v5/integration-modules/{code}
		//integrationModule[integrations][delivery]["actions"]["print"]
		prent(['ssss', $res]);
		/*
		https://tempusshop.retailcrm.ru
		pjcHwE4XBR4DoHGayZb4kB71hjLTCoT9
		*/
		return $res;
	}

    /*public function send($action, $data = [], $method = "GET", $header = []){
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
    }*/

}
