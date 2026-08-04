<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

CModule::IncludeModule('panel.manager');

class checkFBO{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;

		$this->auth = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
    }

	public function run(){

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		$this->arLog = array();
		$this->arLog['TIME_START'] = date('H:i:s');
		$date = date('Y-m-d');

		$startT = microtime(true);


		$this->getItemsWB();
		$this->getItems();
		$this->getSebes();
		$this->getCiBrandID();
		die();
		$this->GetTurnoverWB(3);

		$this->checkFBOStock();
		$itemsT = microtime(true);

		$this->PrintResult();

		//$this->db->Update("wdhs_WB_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='price'", $err_mess.__LINE__);

	}
	public function GetAgent()	{
		$this->fromMS = array();
		$ms = new MoyskladAPI('s1');
		$msItems = $ms->getAgent("dd5b00b5-2a6e-11ec-0a80-019e000baf07");

		print_r($msItems);
	}
  public function getItemsWB(){
			$base_url = WB_BASE_URL;
			$path = "/api/v1/supplier/stocks";

			//$data_string = array('dateFrom' => date("Y-m-d\TH:i:s"));
			$data_string = array('dateFrom' => '2020-01-01');
			$author = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");
			$api = new RestClient([
				'base_url' => 'https://statistics-api.wildberries.ru',
				'curl_options' => array(
						CURLOPT_POST => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_SSL_VERIFYHOST => false,
						CURLOPT_RETURNTRANSFER => TRUE,
						CURLOPT_HEADER => TRUE,
						CURLOPT_CUSTOMREQUEST => 'GET',
						CURLOPT_HTTPHEADER => array(
								'Content-Type: application/json',
								'Authorization: ' . $author,
						)
				)
		]);
		$path = '/api/v1/supplier/stocks?dateFrom=2020-01-01';
		$str_result = $api->post($path, []);
		//print_r(json_decode($str_result->response,true));
		$arResult = json_decode($str_result->response,true);
		foreach ($arResult as $key => $value) {
			if (isset($this->stockFbo[$value['supplierArticle']])) {
				$this->stockFbo[$value['supplierArticle']] =  intval($this->stockFbo[$value['supplierArticle']]) + intval($value['quantity']);
			} else {
				$this->stockFbo[$value['supplierArticle']] =  intval($value['quantity']);
			}
		}
		// print_r($this->stockFbo);
	}

  public function getItems(){
	    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_WBPRICE","PROPERTY_WBARTICLE2","PROPERTY_TYPEOFSKLAD");
	    $arFilter = Array(
	      "IBLOCK_ID" => CProSet::IB_CATALOG,
				"!PROPERTY_WBARTICLE2" => false,
	      //"ID" => 5045,
				//"SECTION_ID" => 558
	      //"ID" => 178901
	    );
			//$arFilter["!ID"] = 14124;
	    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);


	    while ($el = $result->GetNext()){
				if (isset($this->stockFbo[$el["PROPERTY_WBARTICLE2_VALUE"]])) {
					if (empty($el['PROPERTY_WBARTICLE2_VALUE']) or $el['PROPERTY_WBARTICLE2_VALUE'] == '') {
							$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
					}	else if (empty($el['PROPERTY_WBPRICE_VALUE']) or $el['PROPERTY_WBPRICE_VALUE'] == 0) {
							$this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
					} else {
						$arSection = getSectionsElement($el["ID"]);
						// if ($arSection[1]['ID'] == '558') {
			    	$this->items[$el["PROPERTY_WBARTICLE2_VALUE"]] = [
			    		"ID" => $el["ID"],
							"ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
			    		"WB_ARTICLE" => $el["PROPERTY_WBARTICLE2_VALUE"],
			    		"PRICE" => $el["PROPERTY_WBPRICE_VALUE"],
			    	];
					}
		    }
			}
			print_r($this->items);
		}

		public function getSebes(){
			foreach ($this->items as $key => &$v) {
				$tmpPrice = array();
				$strSql = "SELECT price,id FROM ci_price WHERE model = '".$v['ARTICLE']."' AND active_wb = 'Y'" ;
				$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
				while ($row = $results->Fetch()){
					$tmpPrice[$row['id']] = floatval($row['price']);
				}
				foreach($tmpPrice as $k => $value){
			    $minPrice = $value;
			    $price_id = $k;
			    break;
				}
				foreach($tmpPrice as $k => $value){
				    if($value < $minPrice){
				        $minPrice = $value;
				        $price_id = $k;
				    }
				}

				if (!empty($tmpPrice)) {
					$v['SEBES'] = $minPrice;
					$v['PRICE_ID'] = $price_id;
				} else {
					$v['SEBES'] ='';
					$v['PRICE_ID'] = '';
				}
		}
		//print_r($this->items);
	}

	public function getCiBrandID(){
		foreach ($this->items as $key => &$v) {
			$brands = array();
			$brand = '0';
			$strSql = "SELECT brand_id FROM ci_price WHERE model = '".$v['ARTICLE']."'" ;
			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$brands[] = $row['brand_id'];
			}
			foreach($brands as $k => $value){
				$brand = $value;
			}
			if (!empty($brand)) {
				$v['BRAND_ID'] = $brand;
			} else {
				$v['BRAND_ID'] = '';
			}
		}
		//print_r($this->items);
	}

	public function getNewPrice($brand_id,$newprice)
	{
		$price = new CPanelPricelist;
		$analysis = new CPanelAnalysis;
		$objCurrency = new CPanelCurrency;

		$price_id = 'wb';
		$arSettings = array(
			"rate" => 1,
		);

		$arDefaultRRC = json_decode(CProSet::getOption("SETTINGS_RRC"), true)[$price_id];

		$arCurrency = $objCurrency->getDetail($arSettings["currency"]);

		$itemPrice = floatval($newprice);
		$productID = 0;
		$markup = 1;

		//$itemPrice = (float)round($itemPrice, $arSettings["round"]);
		$profile = $analysis->getListByFilter(array("brand_id" => $brand_id,'price_id'=>$price_id));
		// print_r('<br>');
		// print_r($profile);
		if(is_array($profile)){
			$profile = $profile[0];
			$profile["settings"] = json_decode( $profile["settings"], true );

			foreach($profile["settings"] as $key => $arItem){
				if($itemPrice >= $arItem["price_from"] && $itemPrice <= $arItem["price_to"] && $arItem["markup"] > 0)
					$markup = (float)$arItem["markup"];
			}

		}elseif(is_array($arDefaultRRC["rules"])){
			foreach($arDefaultRRC["rules"] as $key => $arItem){
				if($itemPrice >= $arItem["price_from"] && $itemPrice <= $arItem["price_to"] && $arItem["markup"] > 0)
					$markup = (float)$arItem["markup"];
			}
			//prent($markup);
		}
		// print_r('<br>');
		// print_r($markup);
		// print_r('<br>');
		// print_r($itemPrice);
		// print_r('<br>');
		$itemPrice = round($itemPrice * $markup, 0);
		return $itemPrice;
	}

	public function GetTurnoverWB($m)	{
		$this->fromMS = array();
		$ms = new MoyskladAPI('s1');
		$msItems = $ms->getTurnoverWB($m);
		foreach ($msItems as $key => $value) {
			if (!empty($value['assortment']['article']) and ($value['income']['quantity'] != '0')) {
				$this->fromMS[$value['assortment']['article']] = ($value['income']['sum'] / 100) / $value['income']['quantity'];
			}else if ($value['income']['quantity'] != '0') { }
		}
		print_r(count($msItems));
	}

	public function PrintResult()
	{
		// foreach ($this->answer as $key => $value) {
		// 	print_r($value);
		// }
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/fbo/log.txt", print_r(json_encode($this->answer), true).PHP_EOL);
	}

}

(new checkFBO())->run();
