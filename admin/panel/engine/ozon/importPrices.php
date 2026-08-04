<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require( $_SERVER['DOCUMENT_ROOT']."/admin/panel/engine/ozon/classes/PriceManager.php" );
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class OzonImportPrices
{
	public function __construct($cabinet = 'TI'){

		if ( !in_array( $cabinet, ['TI','IP', 'WT']) ) die('WRONG CABINET');

		global $DB;
		$this->loadModules();

		$this->db = $DB;
		$this->CurDB = new DBPanel();

		$this->module = 'price_' . $cabinet;

		$result = $this->CurDB->query("SELECT * FROM ozon_main_settings_{$cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}
		unset($result);
		unset($rows);

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];

		switch ( $cabinet ){
			case 'IP':
				$this->price_prop = "PROPERTY_OZSB_PRICE";
				$this->ci_price_filter = "active_os";
				break;
			case 'TI':
				$this->price_prop = "PROPERTY_PRICE_OZTI";
				$this->ci_price_filter = "active_ozti";
				break;
			case 'WT':
				$this->price_prop = "PROPERTY_OZSB_PRICE";
				$this->ci_price_filter = "active_os";
				break;
		}
		$this->cabinet = $cabinet;

		$this->checkFBOPrice = $this->getFboPrices();
		$this->checkSalePrice = $this->getSalesPrices();
		$this->dynamicPrices = [];
		$this->dynamicPrices = $this->getDynamicPrices();

		$this->excludeModels = $this->getExcludedModels();

		$this->rate = $this->getExchangeRate();
	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule("panel.manager");
  }

	public function run(){

		$argv = $_SERVER['argv'];

		if (isset($_GET['cabinet']) || !empty($_GET['cabinet'])) {
			$CABINET = $_GET['cabinet'];
		} else if (isset($argv[1])) {
			$CABINET = $argv[1];
		} else {
			die('WRONG CABINET');
		}

		if (isset($_GET['source']) || !empty($_GET['source'])) {
			$SOURCE = $_GET['source'];
		} else if (isset($argv[2])) {
			$SOURCE = $argv[2];
		} else {
			$SOURCE = 'undefine';
		}

		$this->arLog = array();
		$this->arLog['TIME_START'] = date('H:i:s');
		$date = date('Y-m-d');

		$timeStart = date('Y.m.d G:i:s');


		$in = array(
			"source	" => "'".$SOURCE."'",
			"script	" => "'price_TI'",
			"time	" => "'".$timeStart."'",
			"status	" => "'RUN'",
		);

		$fields = implode(",", array_keys($in));
		$values = implode(",",$in);

		$sql = "INSERT INTO ozon_tech_log ($fields) VALUES ($values)";
		$this->CurDB->query($sql);

		//Агент-Инфо
		$arStat = [
			'status' => 'PROCESS',
			'status_text' => 'Запуск скрипта',
			'percent' => 0,
			'time_start' => $timeStart,
		];
		$this->updateStatus($this->module, $arStat);

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение товаров из БТ', 'percent' => 10]);

		$startT = microtime(true);
		$this->getItems();
		print_r(count($this->items));
		print_r('###');

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение себесов товаров', 'percent' => 10]);

		$this->getSebes();
		$itemsT = microtime(true);

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Создаем массив для выгрузки', 'percent' => 30]);

    $this->BuildArray();
		$whT = microtime(true);
		print_r(count($this->items));

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Выгрузка цен на OZON', 'percent' => 50]);


		$this->UploadPrices();
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

		$this->arLog['TIME_POINTS'] = [
			'TOTAL' => round($totalT,2),
			'GET_ITEMS' => round($itemsT - $startT,2),
			'PREPARE_ITEMS' => round($whT - $itemsT,2),
		];

		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/price/shows/$date.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);

		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/price/shows/$date.txt", print_r('#SPLIT#',true).PHP_EOL,FILE_APPEND);
	}

  public function getItems(){

    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_PRICE_OZTI","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD","PROPERTY_OZSB_PRICE");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
      //"ID" => 13263,
			//"SECTION_ID" => 558
			"=PROPERTY_OZON_ACTIVE_VALUE" => 'Да'
      //"ID" => 178901
    );
		//$arFilter["!ID"] = 14124;
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);


    while ($el = $result->GetNext()){
			// if ( in_array($el['PROPERTY_CML2_ARTICLE_VALUE'], $this->excludeModels) ) continue;
			if (isset($this->checkFBOPrice[$el['PROPERTY_CML2_ARTICLE_VALUE']])) {
				// $price = $this->checkFBOPrice[$el['PROPERTY_CML2_ARTICLE_VALUE']];
				$price = $el["{$this->price_prop}_VALUE"];
			} else {
				$price = $el["{$this->price_prop}_VALUE"];
			}

			if ( isset($this->dynamicPrices[$el['PROPERTY_CML2_ARTICLE_VALUE']]) ){
				$price = $this->dynamicPrices[ $el['PROPERTY_CML2_ARTICLE_VALUE'] ];
			}




			if (empty($el['PROPERTY_WBARTICLE_VALUE']) or $el['PROPERTY_WBARTICLE_VALUE'] == '') {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
			}	else if (empty($el["{$this->price_prop}_VALUE"]) or $el["{$this->price_prop}_VALUE"] == 0) {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
			//} else if (isset($this->checkSalePrice[$el['PROPERTY_CML2_ARTICLE_VALUE']]) && intval($this->checkSalePrice[$el['PROPERTY_CML2_ARTICLE_VALUE']]) >= $price) {
		  }
			// else if (isset($this->checkSalePrice[$el['PROPERTY_CML2_ARTICLE_VALUE']])) {
			// 		$this->arLog['GET_ITEMS']['ERRORS']['SET_BY_SALE_MODULE'][] = $el['ID'];
			// }
			 else {
				$arSection = getSectionsElement($el["ID"]);
				// if ($arSection[1]['ID'] == '558') {

				// $price = $el["PROPERTY_PRICE_OZTI_VALUE"];
	    	$this->items[$el["PROPERTY_WBARTICLE_VALUE"]] = [
	    		"ID" => $el["ID"],
					"ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
	    		"OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_VALUE"],
	    		"PRICE" => $price,
	    	];
			}
    }


		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/price/tmp/arUpdatePrice.txt", print_r($this->items, true));
	}

	private function getExcludedModels():array
	{
		$result = [];
		$path = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/configs/excludedPrices.json';

		if ( !file_exists($path) ) return $result;

		$json = file_get_contents($path);
		$result = json_decode($json, 1);

		return $result;
	}

	private function getFboPrices():array
	{
		if ( $this->cabinet == 'WT' ) return [];

		$rows = $this->CurDB->select(['*'], "ozon_fbo_price_{$this->cabinet}")->make();
		$result = [];

		foreach ( $rows as $row ){
			$result[$row['article']] = $row['price'];
		}

		return $result;
	}

	private function getSalesPrices():array
	{
		if ( $this->cabinet == 'WT' ) return [];

		$rows = $this->CurDB->select(['*'], "ozon_sales_prices_{$this->cabinet}")->make();
		$result = [];
		$items = [];

		foreach ( $rows as $row ){
			$items[$row['model']][] = $row['price'];
		}

		foreach ( $items as $key => $row ){
			$result[$key] = min($row);
		}

		return $result;
	}

	private function getDynamicPrices():array
	{
		if ( $this->cabinet == 'WT') return [];

		$rows = $this->CurDB->select(['*'], 'ozon_dp_prices')->where('cabinet', $this->cabinet)->make();
		$result = [];

		foreach ( $rows as $row ){
			$result[ $row['model'] ] = $row['price'];
		}

		return $result;
	}

	public function getSebes(){
			foreach ($this->items as $key => &$v) {
				$tmpPrice = array();
				unset($minPrice);
				unset($price_id);
				// $strSql = "SELECT price, id, count, model, supplier_id FROM ci_price WHERE model = '".$v['ARTICLE']."' AND active_ozti = 'Y' ORDER BY price ASC" ;
				$v_art = $v['ARTICLE'];
				$strSql = "SELECT price, id, count, model, supplier_id FROM ci_price WHERE model = '{$v_art}' AND {$this->ci_price_filter} = 'Y' ORDER BY price ASC" ;
				$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
				while ($row = $results->Fetch()){
					$tmpPrice[$row['id']] = [
						'price' =>floatval($row['price']),
						'model' =>$row['model'],
						'count' => intval($row['count']),
						'supplier_id' => $row['supplier_id']
					];
				}

				$strSql = "SELECT * FROM ci_reserved WHERE ARTICLE = '".$v['ARTICLE']."'";
				$resultRes = $this->db->Query($strSql, false, $err_mess.__LINE__);
				$excludeReserved = [];
				while ( $row = $resultRes->Fetch() ){
					$excludeReserved[$row['ARTICLE']] = $row['RESERVED'];
				}

				$curReserved = null;
				$suppExcl = [];
				foreach($tmpPrice as $k => $value){
					if ( $curReserved != null ){
						if ( ($curReserved - $value['count'] >= 0) && !in_array($value['supplier_id'], $suppExcl) ){
							$curReserved = $curReserved - $value['count'];
							$suppExcl[] = $value['supplier_id'];
						}else{
							$minPrice = $value['price'];
							$price_id = $k;
							break;
						}
					}else{
						if ( isset($excludeReserved[$value['model']]) && ($excludeReserved[$value['model']] - $value['count'] >= 0) ){
							$curReserved = $excludeReserved[$value['model']] - $value['count'];
							$suppExcl[] = $value['supplier_id'];
						}else{
							$minPrice = $value['price'];
							$price_id = $k;
							break;
						}
					}
				}

				if (!empty($tmpPrice) && (!empty($minPrice) && !empty($price_id) )) {
					$v['SEBES'] = $minPrice;
					$v['PRICE_ID'] = $price_id;
				} else {
					$v['SEBES'] ='';
					$v['PRICE_ID'] = '';
				}
		}
	}

  public function BuildArray() {
    foreach ($this->items as $key => &$arItem) {
			$price = $arItem['PRICE'];
			$minPrc = strval(round($arItem['PRICE'] * 0.9));
			$price = $price / $this->rate;

			$item = [
				'auto_action_enabled' => 'DISABLED',
				'currency_code' => ($this->cabinet == 'WT') ? 'BYN' : 'RUB',
				'min_price' => strval($price),
				'offer_id' => $arItem['OZON_ARTICLE'],
				'old_price' => '0',
				'price' => strval($price),
				"price_strategy_enabled" => "DISABLED",
				// "vat" => '0.05'
			];

			if ( in_array($this->cabinet, ['IP', 'TI']) ){
				$item['vat'] = '0.05';
			}else{
				// $item['auto_add_to_ozon_actions_list_enabled'] = 'DISABLED';
				$item['manage_elastic_boosting_through_price'] = false;
			}

			$items[] = $item;

    }

		$this->arUpdatePrice = array_chunk($items,1000);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/price/tmp/arPrice.txt", print_r($this->arUpdatePrice, true));
		// var_dump( count($items) );
		// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/priceUpload.txt", print_r($this->arUpdatePrice, true));
		// die;
  }

	private function getExchangeRate():float
	{
		switch ( $this->cabinet ){
			case 'IP':
				$currency = 'RUB';
				return 1;
				break;
			case 'TI':
				$currency = 'RUB';
				return 1;
				break;
			case 'WT':
				$currency = 'BYN';
				break;
		}

		$result = $this->db->Query("SELECT rate FROM ci_currency WHERE id = '{$currency}'");
		$row = $result->Fetch()["rate"];

		return floatval($row);
	}

	public function UploadPrices(){
		$itter = 40 / count($this->arUpdatePrice);
		$perc = 50;
		foreach ($this->arUpdatePrice as $key => $data) {
			sleep(3);
				$res = $this->curlExecUpload($data,$this->api_url,$this->token,$this->client_id);
				while (!isset($res['result'])) {
					sleep(5);
					$res = $this->curlExecUpload($data,$this->api_url,$this->token,$this->client_id);
				}
				//print_r($res);
				foreach ($res['result'] as $k => $v) {
						if ($v['updated'] == '1') {
							$this->arLog['UPDATE']['GOOD'][] = $v['offer_id'];
						} else {
							//print_r($v);
							$this->arLog['UPDATE']['BAD'][] = ['id' => $v['offer_id'],'errors' => $v['errors']];
						}
				}

				 // print_r('<br>');
				 // print_r($res);
				 // print_r('<br>');
				$perc = $perc + round($itter,0);
				if (intval($perc) > 100) { $perc = 99;}
				//Агент-Инфо
				$this->updateStatus($this->module, ['status_text' => 'Выгрузка цен на OZON', 'percent' => $perc ]);

		}

	}

	public function curlExecUpload($data,$url,$token,$client_id) {
		if (empty($data)) {
			return 'error';
		}
		file_put_contents('/var/www/bitrix_logs/ozon/timing.txt', print_r(['curlExecUpload2', date('Y-m-d H:i:s')], true), 8);
		$data_string = json_encode(array('prices'=>$data));
		$ch = curl_init($url . '/v1/product/import/prices');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Api-Key:' . $token,
			'Client-Id:' . $client_id,
			'Content-Type:application/json'
		));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		if ( $this->cabinet == 'WT'){
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		}
		$res = curl_exec($ch);
		curl_close($ch);

		$res = json_decode($res, true);

		if ( $this->cabinet == 'WT' ){
			file_put_contents(
				"/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/pricesRES_DATA.txt",
				print_r($data, true),
				FILE_APPEND
			);
			file_put_contents(
				"/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/pricesRES.txt",
				print_r($res, true),
				FILE_APPEND
			);
		}

		return $res;
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

( new OzonImportPrices( $argv[1] ) )->run();
