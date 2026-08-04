<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class OzonImportStocks{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;
		$strSql = "SELECT * FROM wdhs_ozon_main_settings";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $arSetting[$row['name']] = $row['value'];
		}

		$strSql = "SELECT * FROM wdhs_ozon_fbo_stock";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$this->checkFBOStock[$row['article']] = "Y";
		}

		$strSql = "SELECT * FROM ci_price_quarantine";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$this->quarantine[$row['ARTICLE']] = "Y";
		}

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];

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
    $this->GetWarehouseListFromOzon();
    $this->BuildArray();
		$whT = microtime(true);
    $this->UploadStocks();
		$endT = microtime(true);
		$totalT = $endT - $startT;

		$this->arLog['TIME_POINTS'] = [
			'TOTAL' => round($totalT,2),
			'GET_ITEMS' => round($itemsT - $startT,2),
			'PREPARE_ITEMS' => round($whT - $itemsT,2),
		];
		$this->db->Update("wdhs_ozon_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='stock'", $err_mess.__LINE__);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stocks/shows/$date.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stocks/shows/$date.txt", print_r('#SPLIT#',true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stocks/tmp/UpdateStockResult.txt", print_r('START', true));
	}

  public function getItems(){

		$this->db->Update("wdhs_ozon_upload_status", array("status" => "'INCOMPLETE'","percent" => "'0'"), "WHERE agent = 'stock'", $err_mess.__LINE__);

    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_AVAILABILITY_RU","PROPERTY_CML2_ARTICLE","CATALOG_QUANTITY","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
      "ID" => 203704,
			//"SECTION_ID" => 558
			"PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
      //"ID" => 178901
    );
		//$arFilter["!ID"] = 14124;
    //Array("nPageSize"=>50)
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){
			// $test[] = $el;
			if (empty($el['PROPERTY_WBARTICLE_VALUE']) or $el['PROPERTY_WBARTICLE_VALUE'] == '') {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
			} else {
				if ($el['PROPERTY_AVAILABILITY_RU_VALUE'] == 'Нет в наличии') {
					$el["CATALOG_QUANTITY"] = 0;
				}
					$arSection = getSectionsElement($el["ID"]);
				// if ($arSection[1]['ID'] == '558') {
		    	$this->items[] = [
		    		"ID" => $el["ID"],
		    		"OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_VALUE"],
		    		"QUANTITY" => $el["CATALOG_QUANTITY"],
						"ARTICLE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
		    	];
				// }
			}
    }
		print_r($this->items);
		$this->db->Update("wdhs_ozon_upload_status", array("percent" => '10'), "WHERE agent='stock'", $err_mess.__LINE__);

		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stocks/tmp/arUpdateStock.txt", print_r($this->items, true));
	}

  public function GetWarehouseListFromOzon() {
    $data = array('filter' => array(),'limit' => 100,"offset"=>0);

    $ch = curl_init($this->api_url . '/v1/warehouse/list');
  	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
  		'Api-Key:' . $this->token,
  		'Client-Id:' . $this->client_id,
  		'Content-Type:application/json'
  	));
  	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
  	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  	curl_setopt($ch, CURLOPT_HEADER, false);
  	$res = curl_exec($ch);
  	curl_close($ch);

  	$res = json_decode($res, true);

    if (!empty($res['result'])) {
      foreach ($res['result'] as $k => $v) {
        if ($v['status'] == 'created' and $v['name'] != 'OZON') {
          $this->arWarehouseOzon[] = $v['warehouse_id'];
        }
      }
    }
		$this->db->Update("wdhs_ozon_upload_status", array("percent" => '15'), "WHERE agent='stock'", $err_mess.__LINE__);
  }

  public function BuildArray() {
    //print_r($this->arWarehouseOzon);
    foreach ($this->items as $key => &$arItem) {
	      $arWarehouse = self::getWarehouseItem($arItem['ID'], 's1');
	      foreach ($this->arWarehouseOzon as $key => $wh) {
						if ($arWarehouse[$wh]) {
							$strSql = "SELECT * FROM ci_price WHERE model = '".$arItem['ARTICLE']."' AND active_os = 'Y'";
							$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
							$i = 0;
							while ($row = $results->Fetch()){
								$i++;
							}
							if ($i == 0) {
								$stock_res = 0;
							} else if (isset($this->quarantine[$arItem['ARTICLE']])) {
								$stock_res = 0;
							} else {
								if (intval($arItem['QUANTITY']) > 0) {
								  $stock_res = intval($arItem['QUANTITY']) + 1;
								} else {
									$stock_res = 0;
								}
							}
							if (isset($this->checkFBOStock[$arItem['ARTICLE']])) {
								$stock_res = 0;
							}
			        $arStock = array(
			          "offer_id" => $arItem['OZON_ARTICLE'],
			          "stock" => $stock_res,
			          "warehouse_id" => $wh
			        );
      			} else {
							$arStock = array(
			          "offer_id" => $arItem['OZON_ARTICLE'],
			          "stock" => 0,
			          "warehouse_id" => $wh
			        );
						}
						$items[] = $arStock;
	    }
			$this->arUpdateStock = array_chunk($items,100);
			$this->db->Update("wdhs_ozon_upload_status", array("percent" => '20'), "WHERE agent='stock'", $err_mess.__LINE__);
			print_r($this->arUpdateStock);
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stocks/tmp/arStock.txt", print_r($this->arUpdateStock, true));
	  }
	}

	public function UploadStocks(){
		$itter = 80 / count($this->arUpdateStock);
		$perc = 20;
		foreach ($this->arUpdateStock as $key => $data) {
			$data_string = json_encode(array('stocks'=>$data));
			$ch = curl_init($this->api_url . '/v2/products/stocks');
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
				print_r($res);
				foreach ($res['result'] as $k => $v) {
						if ($v['updated'] == '1') {
							$this->arLog['UPDATE']['GOOD'][] = $v['offer_id'];
						} else {
							$this->arLog['UPDATE']['BAD'][] = ['id' => $v['offer_id'],'errors' => $v['errors']];
						}
				}
				file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stocks/tmp/UpdateStockResult.txt", print_r($res, true),FILE_APPEND);
				$perc = $perc + round($itter,0);
				if (intval($perc) > 100) { $perc = 99;}
				$this->db->Update("wdhs_ozon_upload_status", array("percent" => $perc), "WHERE agent='stock'", $err_mess.__LINE__);
		}

	}


  public static function getWarehouseItem($ID = 0, $SITE_ID = "s1") {
    global $DB;

    $arWarehouse = [];

    $arSelect = Array("ID", "PROPERTY_TYPEOFSKLAD");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
      "ID" => $ID
    );
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){
      $types = $el['PROPERTY_TYPEOFSKLAD_VALUE'];
    }

    if(CModule::IncludeModule('panel.manager')){
      $sklads = TYPE_SKLAD_CONST;
    }
    if ($sklads) {
      foreach ($types as $value) {
        if ($sklads[$value]) {
          $arWarehouse[$sklads[$value]] = true;
        }
      }
    }
    return $arWarehouse;
  }

}


(new OzonImportStocks())->run();
