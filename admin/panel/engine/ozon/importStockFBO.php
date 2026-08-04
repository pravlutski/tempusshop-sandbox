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
	public function __construct($cabinet){
		if (empty($cabinet) || $cabinet == '') {
			die('need cabinet_id');
		}
		global $DB;
		$this->loadModules();
		$this->cabinet = $cabinet;
		$this->CurDB = new DBPanel();
		$this->db = $DB;

		$this->module = 'checkFbo_' . $this->cabinet;

		$this->controlArray = array();
		$this->retrun = array();
		print_r("stock");
		$result = $this->CurDB->query("SELECT * FROM ozon_main_settings_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}
		unset($result);
		unset($rows);

		$result = $this->CurDB->Query("SELECT * FROM ozon_fbo_stock_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->checkCur[$row['article']] = "Y";
			$this->articles[$row['article']] = 'Y';
		}
		unset($result);
		unset($rows);

		$result = $this->CurDB->query("SELECT * FROM ozon_fbo_stock_prev_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->checkPrev[$row['article']] = "Y";
			$this->articles[$row['article']] = 'Y';
		}
		unset($result);
		unset($rows);

		$strSql = "SELECT * FROM ci_price_quarantine";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$this->quarantine[$row['ARTICLE']] = "Y";
		}
		unset($result);
		unset($rows);

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получаем данные для выгрузки остатков', 'percent' => 85]);

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

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Готовим массив выгрузки остатков', 'percent' => 90]);

		if (!empty($this->articles)){
			$this->getItems();
		}
		$itemsT = microtime(true);
    $this->GetWarehouseListFromOzon();
    $this->BuildArray();
		$whT = microtime(true);

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Выгружаем остатки', 'percent' => 95]);

    $this->UploadStocks();
		$endT = microtime(true);
		$totalT = $endT - $startT;

		$timeEnd = date('Y.m.d G:i:s');
    //Агент-Инфо
		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено',
			'percent' => 100,
			'time_end' => $timeEnd
		];
		$this->updateStatus($this->module, $arStat);


		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/fbo/return.txt", print_r(json_encode($this->retrun), true).PHP_EOL);

	}

  public function getItems(){

    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_AVAILABILITY_RU","PROPERTY_CML2_ARTICLE","CATALOG_QUANTITY","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
			"PROPERTY_CML2_ARTICLE" => array_keys($this->articles),
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
    //print_r($this->items);
    foreach ($this->items as $key => &$arItem) {
	      $arWarehouse = self::getWarehouseItem($arItem['ID'], 's1');
				//print_r($arWarehouse);
				$stockControl = 0;
	      foreach ($this->arWarehouseOzon as $key => $wh) {
						if ($arWarehouse[$wh]) {
						  if (isset($this->checkCur[$arItem['ARTICLE']]) && $wh != '1020001970733000') {
								 $stock_res = 0;
							} else {

								if ($this->cabinet == 'TI') {
									$strSql = "SELECT * FROM ci_price WHERE model = '".$arItem['ARTICLE']."' AND active_ozti = 'Y'";
								} else if ($this->cabinet == 'IP') {
									$strSql = "SELECT * FROM ci_price WHERE model = '".$arItem['ARTICLE']."' AND active_os = 'Y'";
								}


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
										// $stock_res = intval($arItem['QUANTITY']) + 1;
										$stock_res = rand( 4, 10 );
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

	public function updateStatus( string $code, array $arStat ):void
	{
		if ( empty($arStat) ) return;
		$strSql = "UPDATE ozon_agents SET ";
		foreach ($arStat as $field => $value) {
			if ( array_key_last($arStat) == $field ){
				$str = "{$field} = '{$value}'";
			}else{
				$str = "{$field} = '{$value}', ";
			}
			$strSql .= $str;
		}
		$strSql .= " WHERE code = '{$code}'";
		try{
			$this->CurDB->query( $strSql );
		}catch( Throwable $ignored){
			print_r('Не удалось обновить статус' . $ignored . "\n");
		}
	}

}

//
// (new OzonImportStocksFBO())->run();
