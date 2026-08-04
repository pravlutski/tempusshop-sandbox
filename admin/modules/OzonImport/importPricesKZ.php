<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class OzonImportPricesKZ{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;

		$strSql = "SELECT * FROM wdhs_ozon_main_settings";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $arSetting[$row['name']] = $row['value'];
		}

		$strSql = "SELECT * FROM wdhs_ozon_fbo_price";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $this->checkFBOPrice[$row['article']] = $row['price'];
		}

		$this->api_url = $arSetting['api_url'];
		$this->client_id = '1776829';
		$this->token = '00bdecf1-32c2-4b74-aec9-73bee4ff4b4e';

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
		$this->getItems();
		$itemsT = microtime(true);
    $this->BuildArray();
		$whT = microtime(true);
		$this->UploadPrices();
		$endT = microtime(true);
		$totalT = $endT - $startT;

		$this->arLog['TIME_POINTS'] = [
			'TOTAL' => round($totalT,2),
			'GET_ITEMS' => round($itemsT - $startT,2),
			'PREPARE_ITEMS' => round($whT - $itemsT,2),
		];
		// $this->db->Update("wdhs_ozon_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='price'", $err_mess.__LINE__);
		// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/prices/shows/$date.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);
		// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/prices/shows/$date.txt", print_r('#SPLIT#',true).PHP_EOL,FILE_APPEND);
	}

  public function getItems(){

		$strSql = "SELECT model FROM ci_price WHERE active_ozkz = 'Y'";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $models[]  = $row['model'];
		}

	//	$this->db->Update("wdhs_ozon_upload_status", array("status" => "'INCOMPLETE'","percent" => "'0'"), "WHERE agent = 'price'", $err_mess.__LINE__);


    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_PRICE_OZKZ","PROPERTY_WBARTICLE_KZ","PROPERTY_TYPEOFSKLAD");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
      //"ID" => 5045,
			//"SECTION_ID" => 558
			"PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
			"PROPERTY_CML2_ARTICLE" => $models,
      //"ID" => 178901
    );
		//$arFilter["!ID"] = 14124;
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

    while ($el = $result->GetNext()){

			if (empty($el['PROPERTY_WBARTICLE_KZ_VALUE']) or $el['PROPERTY_WBARTICLE_KZ_VALUE'] == '') {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
			}	else if (empty($el['PROPERTY_PRICE_OZKZ_VALUE']) or $el['PROPERTY_PRICE_OZKZ_VALUE'] == 0) {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
			} else {
				$price = $el["PROPERTY_PRICE_OZKZ_VALUE"];

	    	$this->items[] = [
	    		"ID" => $el["ID"],
	    		"OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_KZ_VALUE"],
	    		"PRICE" => $price,
	    	];
			}
    }

		//$this->db->Update("wdhs_ozon_upload_status", array("percent" => '10'), "WHERE agent='price'", $err_mess.__LINE__);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/prices/tmp/arUpdatePrice.txt", print_r($this->items, true));
	}

  public function BuildArray() {
    foreach ($this->items as $key => &$arItem) {
			$items[] =  array(
				'auto_action_enabled' => 'DISABLED',
				'currency_code' => 'RUB',
				'min_price' => '0',
				'offer_id' => $arItem['OZON_ARTICLE'],
				'old_price' => '0',
				'price' => strval($arItem['PRICE']),
				"price_strategy_enabled" => "DISABLED",
			);
    }
		$this->arUpdatePrice = array_chunk($items,1000);
		print_r($this->arUpdatePrice);
		// $this->db->Update("wdhs_ozon_upload_status", array("percent" => '20'), "WHERE agent='price'", $err_mess.__LINE__);
		// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/prices/tmp/arPrice.txt", print_r($this->arUpdatePrice, true));
  }

	public function UploadPrices(){
		$itter = 80 / count($this->arUpdatePrice);
		$perc = 20;
		foreach ($this->arUpdatePrice as $key => $data) {
			$data_string = json_encode(array('prices'=>$data));
			$ch = curl_init($this->api_url . '/v1/product/import/prices');
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Api-Key:' . $this->token,
					'Client-Id:' . $this->client_id,
					'Content-Type:application/json'
				));
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_HEADER, false);
				$res = curl_exec($ch);
				curl_close($ch);

				$res = json_decode($res, true);

				foreach ($res['result'] as $k => $v) {
						if ($v['updated'] == '1') {
							$this->arLog['UPDATE']['GOOD'][] = $v['offer_id'];
						} else {
							$this->arLog['UPDATE']['BAD'][] = ['id' => $v['offer_id'],'errors' => $v['errors']];
						}
				}

				print_r('<br>');
				print_r($res);
				print_r('<br>');
				$perc = $perc + round($itter,0);
				if (intval($perc) > 100) { $perc = 99;}
				$this->db->Update("wdhs_ozon_upload_status", array("percent" => $perc), "WHERE agent='price'", $err_mess.__LINE__);
		}

	}

}

//(new OzonImportPricesKZ())->run();
