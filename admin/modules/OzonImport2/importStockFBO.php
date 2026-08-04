<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class OzonImportStocksFBO{
	public function __construct(){
		global $DB;
		$this->loadModules();
		//$this->metric = new Metric();
		$this->db = $DB;
		$this->controlArray = array();
		$this->retrun = array();
		$strSql = "SELECT * FROM wdhs_ozon_main_settings_new";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $arSetting[$row['name']] = $row['value'];
		}

		$strSql = "SELECT * FROM wdhs_ozon_fbo_stock_new";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$this->checkCur[$row['article']] = "Y";
			$this->articles[$row['article']] = 'Y';
		}

		$strSql = "SELECT * FROM wdhs_ozon_fbo_stock_prev_new";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$this->checkPrev[$row['article']] = "Y";
			$this->articles[$row['article']] = 'Y';
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
		Loader::includeModule("panel.manager");
    }

	public function run(){

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		$this->arLog = array();
		$this->arLog['TIME_START'] = date('H:i:s');
		$date = date('Y-m-d');
		$time = date('H:i:s');
		// //metrica
		// unset($dataMetrica);
		// $dataMetrica = [
		// 		'operation' => 'importStock',
		// ];
		// $this->o_id = $this->metric->OzonBase($dataMetrica);
		// //

		$startT = microtime(true);

		if (!empty($this->articles)){
			$this->getItems();
		}
		$itemsT = microtime(true);
    $this->GetWarehouseListFromOzon();
    $this->BuildArray();
		$whT = microtime(true);
    $this->UploadStocks();
		$endT = microtime(true);
		$totalT = $endT - $startT;
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/TI/fbo/return.txt", print_r(json_encode($this->retrun), true).PHP_EOL);

	}

  public function getItems(){

    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_AVAILABILITY_RU","PROPERTY_CML2_ARTICLE","CATALOG_QUANTITY","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
			"=PROPERTY_CML2_ARTICLE" => array_keys($this->articles),
    );
		//$arFilter["!ID"] = 14124;
    //Array("nPageSize"=>50)
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){
			// $test[] = $el;
			if (empty($el['PROPERTY_WBARTICLE_VALUE']) or $el['PROPERTY_WBARTICLE_VALUE'] == '') {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
			} else {
		    	$this->items[$el['PROPERTY_WBARTICLE_VALUE']] = [
		    		"ID" => $el["ID"],
		    		"OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_VALUE"],
		    		"QUANTITY" => $el["CATALOG_QUANTITY"],
						"ARTICLE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
		    	];
			}
    }
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
  }

  public function BuildArray() {
    print_r($this->items);
    foreach ($this->items as $key => &$arItem) {
	      $arWarehouse = self::getWarehouseItem($arItem['ID'], 's1');
				//print_r($arWarehouse);
				$stockControl = 0;
	      foreach ($this->arWarehouseOzon as $key => $wh) {
						if ($arWarehouse[$wh]) {
						  if (isset($this->checkCur[$arItem['ARTICLE']])) {
								 $stock_res = 0;
							} else {
								$strSql = "SELECT * FROM ci_price WHERE model = '".$arItem['ARTICLE']."' AND active_ozti = 'Y'";
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
										$this->retrun[] = $arItem['ARTICLE'];
									} else {
										$stock_res = 0;
									}
								}
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
	  }

		$this->arUpdateStock = array_chunk($items,100);

	}

	public function UploadStocks(){
		$itter = 75 / count($this->arUpdateStock);
		$perc = 25;
		foreach ($this->arUpdateStock as $key => $data) {
			usleep(500000);
			// print_r(count($newdata));
			// die();
			// $data = array_filter($data);
			// $data = array_values($data);
				$res = self::curlExecUpload($data,$this->api_url,$this->token,$this->client_id);
				//print_r($res);
				while (!isset($res['result'])) {
					sleep(5);
					$res = self::curlExecUpload($data,$this->api_url,$this->token,$this->client_id);
				}
				foreach ($res['result'] as $k => $v) {
						if ($v['updated'] == '1') {
							$this->arLog['UPDATE']['GOOD'][] = $v['offer_id'];

						} else {
							$this->arLog['UPDATE']['BAD'][] = ['id' => $v['offer_id'],'errors' => $v['errors']];

						}

				}

				$perc = $perc + $itter;
				if (intval($perc) > 100) { $perc = 99;}
		}

	}

	public static function curlExecUpload($data,$url,$token,$client_id) {
	    if (empty($data)) {
	        return 'error';
	    }
	    $data_string = json_encode(array('stocks' => $data), JSON_UNESCAPED_UNICODE);
	    $ch = curl_init($url . '/v2/products/stocks');
	    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	        'Api-Key:' . $token,
	        'Client-Id:' . $client_id,
	        'Content-Type: application/json'
	    ));
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($ch, CURLOPT_HEADER, false);
	    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	    $res = curl_exec($ch);
	    curl_close($ch);
	    if(curl_errno($ch)) {
	        echo 'Curl error: ' . curl_error($ch);
	    }
	    $res = json_decode($res, true);
	    return $res;
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
      $sklads = TYPE_SKLAD_CONST_TI;
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

// 
// (new OzonImportStocksFBO())->run();
