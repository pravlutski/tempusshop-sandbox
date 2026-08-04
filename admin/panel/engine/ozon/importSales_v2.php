<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class OzonImportSalesClass{
	public function __construct($cabinet){
		if (empty($cabinet) || $cabinet == '') {
			die('need cabinet_id');
		}
		$this->cabinet = $cabinet;
		global $DB;
		$this->loadModules();
		$this->triggers = new TsTriggers();
		$this->CurDB = new DBPanel();
		$this->db = $DB;

		$this->module = 'importSales_' . $this->cabinet;

		$result = $this->CurDB->query("SELECT * FROM ozon_main_settings_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}
		unset($result);
		unset($rows);

		$result = $this->CurDB->query("SELECT * FROM ozon_sales_pi_{$this->cabinet} WHERE pi_sets = 'main'");
	  $rows = $this->CurDB->fetchAll($result);
	  foreach ($rows as $row) {
			$this->minprof = $row['min_profit'];
			$this->minprof_perc = $row['min_profit_perc'];
			$this->unset = $row['unset'];
			$this->com = $row['com'];
			$this->tops = json_decode($row['tops']);
	  }

	  unset($result);
	  unset($rows);

		$result = $this->CurDB->query("SELECT * FROM ozon_top_models");
		$rows = $this->CurDB->fetchAll($result);
		$topsz = [];
		foreach ($rows as $row) {
			$topsz[] = $row['model'];
		}
		if ( !empty($topsz) ) $this->tops = $topsz;
		unset($result);
		unset($rows);

		$result = $this->CurDB->query("SELECT * FROM ozon_sales_info_{$this->cabinet}");
	  $rows = $this->CurDB->fetchAll($result);
    foreach ($rows as $row) {
      $this->curSalePrice[$row['model']][$row['sale_id']] = $row['action_max_price'];
    }

		unset($result);
	  unset($rows);

		$strSql = "SELECT * FROM individual_markups WHERE source = 'ozti'";
		$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
		$this->indivMarkups = [];
		while ( $row = $resultDB->Fetch() ){
			$this->indivMarkups[] = $row['model'];
		}

		$this->salesTop = array();
		$this->reportTop = array();
		$this->checkFBOPrice = array();

		unset($result);
		unset($rows);

		$result = $this->CurDB->query("SELECT * FROM ozon_fbo_price_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->checkFBOPrice[$row['article']] = $row['price'];
		}
		unset($result);
		unset($rows);


		$result = $this->CurDB->query("SELECT * FROM ozon_fbo_sebes_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->checkFBOSebes[$row['model']] = $row['sebes'];
		}
		unset($result);
		unset($rows);

		$strSql = "SELECT * FROM individual_markups WHERE source = 'os'";
    $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
    $this->markups = [];
    while ( $row = $results->Fetch() ){
      $this->markups[$row['model']] = $row['model'];
    }


    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];
		$this->info = array();
		$this->excludeModels = $this->getExcludedModels();

		$this->salesSettings = $this->getSalesSettings();
	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule('panel.manager');
  }

	public function UpdateTmpTable(){
		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		$this->date = date('d.m.Y');
		$this->time = date('H:i:s');
		$this->arLog = array();
		$this->info = array();
		$this->deleteArr = array();
		$this->usesNow = array();
		$timeStart = date('Y.m.d G:i:s');


    //Агент-Инфо
		$arStat = [
			'status' => 'PROCESS',
			'status_text' => 'Запуск скрипта',
			'percent' => 0,
			'time_start' => $timeStart
		];
		$this->updateStatus($this->module, $arStat);


		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/sales/log_3.txt", print_r('start', true));

		$this->getItems();
		$this->getSebes();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение товаров и их себестоимостей', 'percent' => 5]);


		$this->GetActiveSales();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение акций', 'percent' => 10]);

		$this->GetAllSalse();
		//print_r('true');die();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение товаров которые участвуют', 'percent' => 20]);

		$this->GetUsesProducts();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение товаров которые могут учавствовать', 'percent' => 30]);

		$this->GetPotencialProducts();
		//print_r('true');die();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Выполнение расчетов', 'percent' => 50]);

		$this->CheckItemsAll();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Отправляем запросы в OZON', 'percent' => 70]);

		$this->DeactivateSales();
		$this->UsesReplace();
		$this->ActivateSales();

		$this->DeleteNotActiveSales();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Обновляем таблицы', 'percent' => 80]);

		$this->updateSales();
		$this->updatePriceTable();

		$triggers = new TsTriggers();
		// $triggers->SetError(["Акции обновлены [". date('d.m.Y H:i:s')."]\n"]);
		// $triggers->SendTriggerErrors();
		// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/sales/detail/{$this->date}.txt", print_r('###', true).PHP_EOL, FILE_APPEND);
		// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/sales/detail/{$this->date}.txt", print_r(json_encode(array($this->time => $this->detail)), true).PHP_EOL, FILE_APPEND);

		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/sales/log.txt", print_r(json_encode($this->arLog), true));
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/sales/top.txt", print_r(json_encode($this->reportTop), true));

		print_r('<br>');
		foreach ($this->salesActive as $key => $sale) {
			// print_r('<b><a target="_blank" href="/admin/panel/ozon/sales/log.php?ID='.$sale['sale_id'].'">Акция</b> - '.$sale['name'].' ('.$sale['sale_id'].')</a><br>');
			// print_r('УЧАСТВУЮЩИЕ - '.count($this->tmpPorductsM[$sale['sale_id']]['USES']).'<br>');
			// print_r('<span color="green">ОТСАВЛЯЕМ ИДЕАЛЬНЫЕ УСЛОВИЯ</span> - '.count($this->arTmpResU[$sale['sale_id']]['GOOD']).'<br>');
			// print_r('<span color="green">ОТСАВЛЯЕМ C УВЕЛИЧЕНИЕМ ЦЕНЫ</span> - '.count($this->arTmpResU[$sale['sale_id']]['REP']).'<br>');
			// print_r('<span color="red">УДАЛЯЕМ<by/span> - '.count($this->arTmpResU[$sale['sale_id']]['BAD']).'<br>');
			// print_r('ПОТЕНЦИАЛЬНЫЕ - '.count($this->tmpPorducts[$sale['sale_id']]['CAN']).'<br> ');
			// print_r('<span color="green">ДОБАВЛЯЕМ</span> - '.count($this->arTmpRes[$sale['sale_id']]['GOOD']).'<br>');
			// print_r('<span color="red">НЕ ДОБОВЛЯЕМ</span> - '.count($this->arTmpRes[$sale['sale_id']]['BAD']).'<br>');
			// print_r('##############################################<br>');

			print_r("Акция: {$sale['name']} ({$sale['id']})\n");
			print_r("Участвует: " . count($this->tmpPorductsM[$sale['sale_id']]['USES'] ?? []) . "\n");
			print_r("Оставляем (Идеальные условия):" . count($this->arTmpResU[$sale['sale_id']]['GOOD']) . "\n");
			print_r("Оставляем (Увеличение цены):" . count($this->arTmpResU[$sale['sale_id']]['REP']) . "\n");
			print_r("Удаляем: " . count($this->arTmpResU[$sale['sale_id']]['BAD']) . "\n");
			print_r("Потенциальные: " . count($this->tmpPorducts[$sale['sale_id']]['CAN']) . "\n");
			print_r("Добавляем: " . count($this->arTmpRes[$sale['sale_id']]['GOOD']) . "\n");
			print_r("Не добавляем: " . count($this->arTmpRes[$sale['sale_id']]['BAD']) . "\n");
			print_r("#############################################\n");
		}

		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено',
			'percent' => 100,
			'time_end' => date('Y-m-d G:i:s'),
		];
		$this->updateStatus($this->module, $arStat);
	}

  public function getItems(){

		if ($this->cabinet == 'TI') {
			$constPrice = 'PRICE_OZTI';
			$constID = 'OZON_ID_TI';
			$this->constPriceType = 'ozti';
		} else if ($this->cabinet == 'IP') {
			$constPrice = 'OZSB_PRICE';
			$constID = 'OZON_ID';
			$this->constPriceType = 'os';
		} else {
			die('WRONG CONST');
		}

    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_".$constPrice."","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD","PROPERTY_".$constID."","PROPERTY_BRAND");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
			"PROPERTY_OZON_ACTIVE_VALUE" => 'Да'
    );

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

    while ($el = $result->GetNext()){
			if ( in_array($el['PROPERTY_CML2_ARTICLE_VALUE'], $this->excludeModels) ) continue;
			if (empty($el['PROPERTY_WBARTICLE_VALUE']) or $el['PROPERTY_WBARTICLE_VALUE'] == '') {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
			}	else if (empty($el['PROPERTY_'.$constPrice.'_VALUE']) or $el['PROPERTY_'.$constPrice.'_VALUE'] == 0) {
					$this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
			} else {
				$arSection = getSectionsElement($el["ID"]);

				if (isset($this->checkFBOPrice[$el["PROPERTY_CML2_ARTICLE_VALUE"]]) && !empty($this->checkFBOPrice[$el["PROPERTY_CML2_ARTICLE_VALUE"]]) ){
					$price = $this->checkFBOPrice[$el["PROPERTY_CML2_ARTICLE_VALUE"]];
					$fboStatus = 'да';
				} else {
					$price = $el["PROPERTY_".$constPrice."_VALUE"];
					$fboStatus = 'нет';
				}

	    	$this->items[$el["PROPERTY_".$constID."_VALUE"]] = [
	    		"ID" => $el["ID"],
					"PRICE" => $price,
					"MODEL" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
	    		"OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_VALUE"],
	    		"OZON_ID" => $el["PROPERTY_".$constID."_VALUE"],
					"BRAND" => trim($el["PROPERTY_BRAND_VALUE"]),
					"FBO" => $fboStatus
	    	];
			}
    }

		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/sales/tmp/arUpdateSale.txt", print_r($this->items, true));
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


	public function getSebes(){ // Можно опти
		foreach ($this->items as $key => &$v) {
			$tmpPrice = array();
			$strSql = "SELECT price, id, count, model, supplier_id FROM ci_price WHERE model = '".$v['MODEL']."' AND active_".$this->constPriceType." = 'Y' ORDER BY price ASC";

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

			if ( !empty($tmpPrice) && !empty($minPrice) ) {
				$v['SEBES'] = $minPrice;
			} else {
				$v['SEBES'] = '';
			}

			if (isset($this->checkFBOSebes[$v['MODEL']])) {
				$v['SEBES'] = $this->checkFBOSebes[$v['MODEL']];
			}
			if ( empty($v['SEBES']) ){
				unset( $this->items[$key] );
				continue;
			}
		}
	}

	public function GetActiveSales() {
		$dateStart = date('d.m.Y');
		$result = $this->CurDB->query("SELECT * FROM ozon_sales_{$this->cabinet} WHERE STR_TO_DATE(date_start, '%d.%m.%Y') <= STR_TO_DATE('".$dateStart."', '%d.%m.%Y')");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->salesActive[$row['sale_id']] = $row;
			$this->salesActiveNotSort[$row['sale_id']] = $row;
		}

		if (!empty($this->salesActive)) {
			foreach ($this->salesActive as $key => $sale) {
				if (!empty($this->unset) && $this->unset != '') {
					if (strpos($this->unset, ',') !== false) {
						$this->salesActive[$key]['brand_unset'] = explode(',',trim($this->unset));
					} else {
						$this->salesActive[$key]['brand_unset'][] = trim($this->unset);
					}
				} else {
					$this->salesActive[$key]['brand_unset'] = array();
				}
				if (empty($sale['sort']) && $sale['sort'] != '0') {
					print_r('АКЦИЯ '.$sale['name'].' исключена, не заполнена сортировка!');
					unset($this->salesActive[$key]);
				}
				$this->arTmpRes[$sale['sale_id']]['GOOD'] = array();
				$this->arTmpRes[$sale['sale_id']]['BAD'] = array();
				$this->arTmpResU[$sale['sale_id']]['GOOD'] = array();
				$this->arTmpResU[$sale['sale_id']]['REP'] = array();
				$this->arTmpResU[$sale['sale_id']]['BAD'] = array();
				$this->usesNow[$sale['sale_id']] = array();
			}
		}

	}

	public function GetAllSalse()
	{
		$this->NotActiveSales = array();
		$this->AllSale = array();

		$res = $this->request(
			url: $this->api_url . '/v1/actions',
			headers: $this->getHeaders(),
			data: false,
		);

		foreach ($res['result'] as $k=>$r) {
			$this->AllSale[] = $r;
		}
		foreach ($this->AllSale as $key => $value) {
			if (!isset($this->salesActiveNotSort[$value['id']])) {
				$this->NotActiveSales[] = $value;
			}
		}
		foreach ($this->NotActiveSales as $k => $v) {

			if (DateTime::createFromFormat(DateTime::ATOM, $v['date_start']) !== false) {
		      $dateFix = DateTime::createFromFormat(DateTime::ATOM, $v['date_start']);
		      $v['date_start'] = $dateFix->format('d.m.Y H:i:s');
		  }
		  if (DateTime::createFromFormat(DateTime::ATOM, $v['date_end']) !== false) {
		      $dateFix = DateTime::createFromFormat(DateTime::ATOM, $v['date_end']);
		      $v['date_end'] = $dateFix->format('d.m.Y H:i:s');
		  }
		  $dateTimeF = new DateTime($v['date_start']);
		  $dateTimeT = new DateTime($v['date_end']);
			$dateTimeF->modify('+3 hours');
			$dateTimeT->modify('+3 hours');
		  $dateFrom = $dateTimeF->format('d.m.Y H:i:s');
		  $dateTo = $dateTimeT->format('d.m.Y H:i:s');

			$currentDate = new DateTime();
			$currentDate = $currentDate->format('d.m.Y H:i:s');
			if ($currentDate > $dateTimeF) {

				$in = array(
					"sale_id" => $v['id'],
					"sort" => "0",
					"active" => "'".$v['is_participating']."'",
					"name" => "'".trim($v['title'])."'",
					"date_start" => "'".$dateFrom."'",
					"date_end" => "'".$dateTo."'",
					"perc" => "1",
					"potencial" => "'".$v['potential_products_count']."'",
					"uses" => "'".$v['participating_products_count']."'",
				);


				$fields = implode(",", array_keys($in));
				$values = implode(",",$in);

				$sql = "INSERT INTO ozon_sales_{$this->cabinet} ($fields) VALUES ($values)";
				$this->CurDB->query($sql);

				$this->salesActive[$v['id']] = array(
					"sale_id" => $v['id'],
					"sort" => 0,
					"active" => $v['is_participating'],
					"name" => trim($v['title']),
					"date_start" => $dateFrom,
					"date_end" => $dateTo,
					"perc" => 1,
					"brand_unset" => array(),
					"potencial" => $v['potential_products_count'],
					"uses" => $v['participating_products_count'],
				);
				$this->arTmpRes[$v['id']]['GOOD'] = array();
				$this->arTmpRes[$v['id']]['BAD'] = array();
				$this->arTmpResU[$v['id']]['GOOD'] = array();
				$this->arTmpResU[$v['id']]['REP'] = array();
				$this->arTmpResU[$v['id']]['BAD'] = array();
				$this->usesNow[$v['id']] = array();
			}
			unset($in);
		}


		usort($this->salesActive, function($a, $b) {
				return $a['sort'] - $b['sort'];
		});
	}
  //текущие
	public function GetUsesProducts()
	{
		$this->NotActiveProduct = array();

		foreach ($this->salesActive as $key => $value) {
			$saleID = $value['sale_id'];
			$i = 0;
			$check = true;
			$this->tmpPorductsM[$saleID]['USES'] = array();
			$last_id = "";

			while ( $check == true ) {
				$data_string = json_encode([
					"action_id" => $saleID,
					"limit" => 1000,
					"last_id" => $last_id
				]);

				$res = $this->request(
					url: $this->api_url . '/v1/actions/products',
					headers: $this->getHeaders(),
					data: $data_string,
				);

				if (isset($res['result'])) {
					if (count($res['result']['products']) < 1000) {
						$check = false;
					} else {
						$check = true;
					}
				} else {
					$check = false;

					print_r("ОШИБКА В ОТВЕТЕ API OZON (".__LINE__.")\n");
					print_r("Response: {$res}\n");
					print_r("Sale ID: {$value['sale_id']}\n");
				}
				foreach ($res['result']['products'] as $key => $value) {
					if (!isset($this->items[$value['id']])) {
						$this->NotActiveProduct[$saleID][] = [
							'product_id' => $value['id'],
							'need_price' => $value['max_action_price'],
							'check_price' => $value['action_price'],
							'arr' => $value,
						];
					} else {
						$elastic_price = $this->calculateElasticPrice( item: $value, saleId: $saleID );
						$max_action_price = $elastic_price != false ? $elastic_price : $value['max_action_price'];
						$is_calculated = $elastic_price == false ? false : true;
 						if ( $saleID != 1977747 ) {
							$max_action_price = $value['max_action_price'];
							$is_calculated = false;
						}

						$this->tmpPorductsM[$saleID]['USES'][$value['id']] = [
							'product_id' => $value['id'],
							// 'need_price' => $value['max_action_price'],
							'need_price' => $max_action_price,
							'is_calculated' => $is_calculated,
							'check_price' => $value['action_price'],
							'arr' => $value,
						];
					}
				}
				$last_id = $res["result"]["last_id"];
				$i=$i+1000;
				sleep(5);
			}
		}
		file_put_contents(
			'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/tmpPorductsM.log',
			print_r( $this->tmpPorductsM, true )
		);
	}

	public function GetPotencialProducts()
	{
		foreach ($this->salesActive as $key => $value) {

			$saleID = $value['sale_id'];
			$this->tmpPorducts[$saleID]['CAN'] = array();
			$i = 0;
			$check = true;
			$last_id = "";

			while ($check == true) {
				$data_string = json_encode([
					"action_id" => $saleID,
					"limit" => 1000,
					"last_id" => $last_id
				]);

				$res = $this->request(
					url: $this->api_url . '/v1/actions/candidates',
					headers: $this->getHeaders(),
					data: $data_string,
				);

				if ( isset($res['code']) && $res['code'] == '8' ) { // Слишком много запросов
					$arStat = [
						'status' => 'COMPLETE',
						'status_text' => $res['message'],
						'percent' => 100,
						'time_end' => date('Y.m.d G:i:s'),
					];
					$this->updateStatus($this->module, $arStat);
					die( $res['message'] . PHP_EOL );
				}
				if (isset($res['result'])) {
					if (count($res['result']['products']) < 1000) {
						$check = false;
					} else {
						$check = true;
					}
				} else {
					$check = false;
					print_r("ОШИБКА В ОТВЕТЕ API OZON\n");
					print_r( $res );
					print_r( PHP_EOL );
					print_r("Sale ID: {$saleID}\n");

					file_put_contents(
						'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/sales_errors/last_sale_error_GPP.log',
						print_r($res, true)
					);

					$triggers = new TsTriggers();
					$triggers->SetError(["ОШИБКА ПРИ ОБНОВЛЕНИИ АКЦИЙ [". date('d.m.Y H:i:s')."]\n Метод: GetPotencialProducts"]);
					$triggers->SendTriggerErrors();
					die();
				}
				foreach ($res['result']['products'] as $key => $value) {
					if ( !isset($this->items[$value['id']]) ) continue;

					$elastic_price = $this->calculateElasticPrice( item: $value, saleId: $saleID );
					$max_action_price = $elastic_price != false ? $elastic_price : $value['max_action_price'];
					$is_calculated = $elastic_price == false ? false : true;
					if ( $saleID != 1977747 ){
						 $max_action_price = $value['max_action_price'];
						 $is_calculated = false;
					}

					$this->tmpPorducts[$saleID]['CAN'][$value['id']] = [
						'product_id' => $value['id'],
						// 'need_price' => $value['max_action_price'],
						'need_price' => $max_action_price,
						'is_calculated' => $is_calculated,
						'check_price' => $value['action_price'],
						'arr' => $value
					];
				}
				$last_id = $res['result']['last_id'];
				$i=$i+1000;
				sleep(3);
			}
		}
		file_put_contents(
			'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/tmpPorducts.log',
			print_r( $this->tmpPorducts, true )
		);
	}

	//new vers
	public function CheckItemsAll()
	{
		$this->check = array();
		$arMegLog = [];
		if ( empty($this->salesActive) ) return;

		foreach ($this->salesActive as $k => $sale) {
			foreach ($this->items as $key => &$item) {

				//UNSET CHECK
				if (in_array($item['OZON_ID'], $this->deleteArr) && !in_array($item['OZON_ID'],$this->usesNow[$sale['sale_id']])) {
					$this->arTmpResU[$sale['sale_id']]['BAD'][$item['OZON_ID']] = [
						'ozon_id' => $item['OZON_ID'],
						'model' => $item['MODEL'],
						'sebes' => $item['SEBES'],
						'price' => $item['PRICE'],
						'needed' => '',
						'perc' => '',
						'skdSebes' => '',
						'com' => '',
						'newCom' => '',
						'merg' => '',
						'min_profit' => '',
					];

					if ( isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']]) ) {
						$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
					} else {
						$curSaleprice = 'отсутствует';
					}

					$bodyLog = [
						'ozon_answer' => $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['arr'],
						'sale' => $sale['name'],
						'sebes' => $item['SEBES'],
						'fbo' => $item['FBO'],
						'cur_sale_price' => '',
						'price_raschet' => '',
						'merg_raschet' => '',
						'cur_price' => '',
						'cur_min_prof' => '',
						'status' => 'delete-prioretet'
					];
					$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
					$in = array(
						"model"	=> "'".$item['MODEL']."'",
						"sale_id"	=> "'".$sale['sale_id']."'",
						"datetime"=> "'".date('Y-m-d H:i:s')."'",
						"body" => "'".$bodyLog."'",
					);
					$fields = implode(",", array_keys($in));
					$values = implode(",",$in);
					$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
					$this->CurDB->query($sql);
					unset($in);
					unset($bodyLog);
					unset($fields);
					unset($values);
					unset($sql);

					$reason = 'Товар удален из акции, т.к. вошел в другую акцию по приоритету';

					$this->arLog[$sale['sale_id']]['DELETE'][$item['OZON_ID']] = [
					'ozon_id' => $item['OZON_ID'],
					'model' => $item['MODEL'],
					'reason' => $reason,
					];
					continue;
				}

				if (in_array($item['BRAND'], $sale['brand_unset'])) {
					$this->arTmpResU[$sale['sale_id']]['BAD'][$item['OZON_ID']] = [
					'ozon_id' => $item['OZON_ID'],
					'model' => $item['MODEL'],
					'sebes' => '',
					'price' => '',
					'needed' => '',
					'perc' => '',
					'skdSebes' => '',
					'com' => '',
					'newCom' => '',
					'merg' => '$merg',
					'min_profit' => '',
					];
					$reason = 'Бренд исключен!';
				} else if ( isset($this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]) && !empty($item['PRICE']) ) {
					$needed = $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['need_price'];
					//СТРОГАЯ ПРОВЕРКА
					if ( !isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']]) ) {
						$this->curSalePrice[$item['MODEL']][$sale['sale_id']] = $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['check_price'];
						$this->info[$item['MODEL']][$sale['sale_id']] = $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['check_price'];
					}


					//идеал
					if ( isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']]) && $this->curSalePrice[$item['MODEL']][$sale['sale_id']] >= $needed ) {

						// $needed = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
						// if ($needed > $item['PRICE']) {
						// 	$needed = $item['PRICE'];
						// }


						$needed = $item['PRICE'] * (1 - intval($sale['perc']) / 100);

						if ($this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['need_price'] < $needed) {
							// $merg внутри этого тела и выше не вычисляется
							$this->arTmpResU[$sale['sale_id']]['BAD'][$item['OZON_ID']] = [
							'ozon_id' => $item['OZON_ID'],
							'model' => $item['MODEL'],
							'sebes' => $item['SEBES'],
							'price' => $item['PRICE'],
							'needed' => $needed,
							'perc' => floatval($sale['perc']),
							'skdSebes' => '',
							'com' => floatval($this->com),
							'newCom' => floatval($this->com) - floatval($sale['skd']),
							'merg' => '',
							'min_profit' => $this->minprof,
							];

							if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
								$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
							} else {
								$curSaleprice = 'отсутствует';
							}

							$bodyLog = [
							'ozon_answer' => $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['arr'],
							'sale' => $sale['name'],
							'fbo' => $item['FBO'],
							'sebes' => $item['SEBES'],
							'cur_sale_price' => $curSaleprice,
							'price_raschet' => $needed,
							'merg_raschet' => $merg,
							'cur_price' => $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['need_price'],
							'cur_min_prof' => $this->minprof,
							'status' => 'delete'
							];

							$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
							$in = array(
							"model"	=> "'".$item['MODEL']."'",
							"sale_id"	=> "'".$sale['sale_id']."'",
							"datetime"=> "'".date('Y-m-d H:i:s')."'",
							"body" => "'".$bodyLog."'",
							);
							$fields = implode(",", array_keys($in));
							$values = implode(",",$in);
							$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
							$this->CurDB->query($sql);
							unset($in);
							unset($bodyLog);
							unset($fields);
							unset($values);
							unset($sql);

							if ($skdSebes > $needed) {
								$reason = 'Условия больше не идеальны. Продаем в минус.<br>Не проходим по мин. цене: ' . $skdSebes . ' > ' . $needed . ' (расчет с учетом уменьшенной коммиссии на ' . $sale['skd'] . ')';
							} else if ($this->minprof > $merg) {
								$reason = 'Условия больше не идеальны. Продаем в минус.<br>Не проходим по минимально маржинальности: ' . $this->minprof . ' > ' . $merg . ' (расчет с учетом уменьшенной коммиссии на ' . $sale['skd'] . ')';
							} else {
								$reason = "Условия больше не идеальны. Продаем в минус.";
							}
							if (in_array($item['MODEL'], $this->indivMarkups)) {
								$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
							}
							$this->arLog[$sale['sale_id']]['DELETE'][$item['OZON_ID']] = [
							'ozon_id' => $item['OZON_ID'],
							'model' => $item['MODEL'],
							'reason' => $reason,
							];
							continue;
						}

						if ( !empty($sale['skd']) ) {
							if (isset($this->checkFBOPrice[$item['MODEL']]) && !empty($sale['skd_fbo'])) {
								$newCom = (floatval($this->com) - $sale['skd'] - $sale['skd_fbo']) / 100;
							} else {
								$newCom = (floatval($this->com) - $sale['skd']) / 100;
							}

							$com = floatval($this->com) / 100;
							$merg = ($needed - $needed * $newCom) - floatval($item['SEBES']);
							$merg_perc = ($needed * ( 1 - $newCom ) - floatval($item['SEBES'])) / floatval($item['SEBES']) * 100; // Маржа в процентах


							if ( isset($sale['perc']) && !empty($sale['perc']) ) {
								$fsb = $item['PRICE'] * (1 - intval($sale['perc']) / 100);
							} else {
								$fsb = $item['PRICE'];
							}
							$skdSebes = ($fsb * (1 - $com)) / (1 - $newCom);

							// $merg_perc - маржа в процентах
							// $merg - маржа плоская
							// $this->minprof - минимальная плоская маржа в рублях
							// $this->min_prof_perc - минимальная маржа в процентах
							// $skdSebes > $needed - никогда не выполняется

							if ( $skdSebes > $needed or $this->minprof > $merg or $this->min_prof_perc > $merg_perc) {
								$this->arTmpResU[$sale['sale_id']]['BAD'][$item['OZON_ID']] = [
									'ozon_id' => $item['OZON_ID'],
									'model' => $item['MODEL'],
									'sebes' => $item['SEBES'],
									'price' => $item['PRICE'],
									'needed' => $needed,
									'perc' => floatval($sale['perc']),
									'skdSebes' => $skdSebes,
									'com' => floatval($this->com),
									'newCom' => floatval($this->com) - $sale['skd'],
									'merg' => $merg,
									'min_profit' => $this->minprof,
								];

								if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
									$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
								} else {
									$curSaleprice = 'отсутствует';
								}

								$bodyLog = [
									'ozon_answer' => $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['arr'],
									'sale' => $sale['name'],
									'fbo' => $item['FBO'],
									'sebes' => $item['SEBES'],
									'cur_sale_price' => $curSaleprice,
									'price_raschet' => $skdSebes,
									'merg_raschet' => $merg,
									'cur_price' => $needed,
									'cur_min_prof' => $this->minprof,
									'status' => 'delete'
								];

								$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
								$in = array(
									"model"	=> "'".$item['MODEL']."'",
									"sale_id"	=> "'".$sale['sale_id']."'",
									"datetime"=> "'".date('Y-m-d H:i:s')."'",
									"body" => "'".$bodyLog."'",
								);
								$fields = implode(",", array_keys($in));
								$values = implode(",",$in);
								$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
								$this->CurDB->query($sql);
								unset($in);
								unset($bodyLog);
								unset($fields);
								unset($values);
								unset($sql);

								if ($skdSebes > $needed) {
									$reason = 'Условия больше не идеальны. Продаем в минус.<br>Не проходим по мин. цене: ' . $skdSebes . ' > ' . $needed . ' (расчет с учетом уменьшенной коммиссии на ' . $sale['skd'] . ')';
								} else if ($this->minprof > $merg) {
									$reason = 'Условия больше не идеальны. Продаем в минус.<br>Не проходим по минимально маржинальности: ' . $this->minprof . ' > ' . $merg . ' (расчет с учетом уменьшенной коммиссии на ' . $sale['skd'] . ')';
								} else {
									$reason = "Ошибка неизвестна.";
								}
								if (in_array($item['MODEL'], $this->indivMarkups)) {
									$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
								}
								$this->arLog[$sale['sale_id']]['DELETE'][$item['OZON_ID']] = [
									'ozon_id' => $item['OZON_ID'],
									'model' => $item['MODEL'],
									'reason' => $reason,
								];
							} else {
								$this->arTmpResU[$sale['sale_id']]['GOOD'][$item['OZON_ID']] = [
									'ozon_id' => $item['OZON_ID'],
									'model' => $item['MODEL'],
									'sebes' => $item['SEBES'],
									'price' => $item['PRICE'],
									'needed' => $needed,
									'perc' => floatval($sale['perc']),
									'skdSebes' => $skdSebes,
									'com' => floatval($this->com),
									'newCom' => floatval($this->com) - $sale['skd'],
									'merg' => $merg,
									'min_profit' => $this->minprof,
								];
								$this->forUpdatePrice[] = [
									'sale_id' => $sale['sale_id'],
									'model' => $item['MODEL'],
									'price' => $needed,
								];

								$this->info[$item['MODEL']][$sale['sale_id']] = $needed;

								if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
									$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
								} else {
									$curSaleprice = 'отсутствует';
								}

								$bodyLog = [
									'ozon_answer' => $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['arr'],
									'sale' => $sale['name'],
									'fbo' => $item['FBO'],
									'sebes' => $item['SEBES'],
									'cur_sale_price' => $curSaleprice,
									'price_raschet' => $skdSebes,
									'merg_raschet' => $merg,
									'cur_price' => $needed,
									'cur_min_prof' => $this->minprof,
									'status' => 'stay'
								];
								$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
								$in = array(
									"model"	=> "'".$item['MODEL']."'",
									"sale_id"	=> "'".$sale['sale_id']."'",
									"datetime"=> "'".date('Y-m-d H:i:s')."'",
									"body" => "'".$bodyLog."'",
								);
								$fields = implode(",", array_keys($in));
								$values = implode(",",$in);
								$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
								$this->CurDB->query($sql);
								unset($in);
								unset($bodyLog);
								unset($fields);
								unset($values);
								unset($sql);

								if ($sale['sort'] != '0') {
									$this->deleteArr[] = $item['OZON_ID'];
									$this->usesNow[$sale['sale_id']][] = $item['OZON_ID'];
								}
								if (in_array($item['MODEL'], $this->tops)) {
									$this->salesTop[$sale['sale_id']][] = ['fbo' => $item['FBO'], 'model'=>$item['MODEL'], 'sale'=> $sale['name']];
									$this->reportTop[$item['MODEL']] = ['fbo' => $item['FBO'], 'model'=>$item['MODEL'], 'sale'=> $sale['name']];
								}

								if ($skdSebes <= $needed && $this->minprof <= $merg && $this->minprof_perc <= $merg_perc) {
									$reason = 'Идеальные условия.<br>Проходим по всем сво-вам: ' . $skdSebes . ' <= ' . $needed . ' и ' . $this->minprof . ' <= ' . $merg . ' и ' . $this->minprof_perc . ' <= ' . $merg_perc . ' (расчет с учетом уменьшенной коммиссии на ' . $sale['skd'] . ')';
								} else {
									$reason = "Неизвестный случай.";
								}

								if (in_array($item['MODEL'], $this->indivMarkups)) {
									$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
								}

								$this->arLog[$sale['sale_id']]['STAY'][$item['OZON_ID']] = [
									'ozon_id' => $item['OZON_ID'],
									'model' => $item['MODEL'],
									'reason' => $reason,
								];

								$this->check[$item['OZON_ID']] = 1;
							}
						}
						else { // Если не заполнено поле -КОМ
							$com = floatval($this->com) / 100;
							$merg = ($needed - $needed * $com) - floatval($item['SEBES']);
							$merg_perc = ($needed * ( 1 - $com ) - floatval($item['SEBES'])) / floatval($item['SEBES']) * 100; // Маржа в процентах

							if ( isset($sale['perc']) && !empty($sale['perc']) ) {
								$fsb = $item['PRICE'] * (1 - intval($sale['perc']) / 100);
							} else {
								$fsb = $item['PRICE'];
							}

							if ( isset($this->checkFBOPrice[$item['MODEL']]) && !empty($sale['skd_fbo']) ) {
								$newCom = (floatval($this->com) - $sale['skd_fbo']) / 100;
								$fsb = ($fsb * (1 - $com)) / (1 - $newCom);
							}

							$res_update = 0;

							// $fsb в текущей реализации всегда равно $needed, так как они считаются абсолютно одинаково

							if ( $fsb <= $needed && $this->minprof > $merg ) {
								$newPrice = ($this->minprof + $item['SEBES']) / (1 - $com);

								if ( $newPrice <= $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['need_price'] ) {
									$needed = $newPrice;
									$fsb = $newPrice;
									$merg = $this->minprof;
									$res_update = 1;
								}
							}
							else if ( $this->minprof_perc > $merg_perc ) {
								$newPrice = ( (1 + $this->minprof_perc / 100 ) * $item['SEBES'] ) / ( 1 - $com );
								// $newPrice = ($this->minprof + $item['SEBES']) / (1 - $com);
								if ( $newPrice <= $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['need_price'] ) {
									$needed = $newPrice;
									$fsb = $newPrice;
									$merg = $this->minprof;
									$merg_perc = $this->minprof_perc;
									$res_update = 2;
								}
							}

							if ($fsb > $needed or $this->minprof > $merg or $this->minprof_perc > $merg_perc ) {
								$this->arTmpResU[$sale['sale_id']]['BAD'][$item['OZON_ID']] = [
									'ozon_id' => $item['OZON_ID'],
									'model' => $item['MODEL'],
									'sebes' => $item['SEBES'],
									'price' => $itme['PRICE'],
									'needed' => $needed,
									'perc' => $sale['perc'],
									'com' => $this->com,
									'merg' => $merg,
									'min_profit' => $this->minprof,
								];

								if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
									$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
								} else {
									$curSaleprice = 'отсутствует';
								}

								$bodyLog = [
									'ozon_answer' => $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['arr'],
									'sale' => $sale['name'],
									'fbo' => $item['FBO'],
									'sebes' => $item['SEBES'],
									'cur_sale_price' => $curSaleprice,
									'price_raschet' => $fsb,
									'merg_raschet' => $merg,
									'cur_price' => $needed,
									'cur_min_prof' => $this->minprof,
									'status' => 'delete'
								];
								$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
								$in = array(
									"model"	=> "'".$item['MODEL']."'",
									"sale_id"	=> "'".$sale['sale_id']."'",
									"datetime"=> "'".date('Y-m-d H:i:s')."'",
									"body" => "'".$bodyLog."'",
								);
								$fields = implode(",", array_keys($in));
								$values = implode(",",$in);
								$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
								$this->CurDB->query($sql);
								unset($in);
								unset($bodyLog);
								unset($fields);
								unset($values);
								unset($sql);
								if ($fsb  > $needed) {
									$reason = 'Условия больше не идеальны. Продаем в минус.<br>Не проходим по мин. цене: ' . $fsb . ' > ' . $needed . '';
								} else if ($this->minprof > $merg) {
									$reason = 'Условия больше не идеальны. Продаем в минус.<br>Не проходим по минимально маржинальности: ' . $this->minprof . ' > ' . $merg . '';
								}	else if ($this->minprof > $merg) {
									$reason = 'Условия больше не идеальны. Продаем в минус.<br>Не проходим по минимальноЙ процентной маржинальности: ' . $this->minprof_perc . ' > ' . $merg_perc . '';
								} else {
									$reason = "Ошибка неизвестна.";
								}

								if (in_array($item['MODEL'], $this->indivMarkups)) {
									$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
								}

								$this->arLog[$sale['sale_id']]['DELETE'][$item['OZON_ID']] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'reason' => $reason,
								];
							}
							 else {
								$this->arTmpResU[$sale['sale_id']]['GOOD'][$item['OZON_ID']] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'sebes' => $item['SEBES'],
								'price' => $fsb,
								'needed' => $needed,
								'perc' => $sale['perc'],
								'com' => $this->com,
								'merg' => $merg,
								'min_profit' => $this->minprof,
								];
								$this->forUpdatePrice[] = [
								'sale_id' => $sale['sale_id'],
								'model' => $item['MODEL'],
								'price' => $needed,
								];
								$this->info[$item['MODEL']][$sale['sale_id']] = $needed;

								if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
									$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
								} else {
									$curSaleprice = 'отсутствует';
								}

								$bodyLog = [
								'ozon_answer' => $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['arr'],
								'sale' => $sale['name'],
								'fbo' => $item['FBO'],
								'sebes' => $item['SEBES'],
								'cur_sale_price' => $curSaleprice,
								'price_raschet' => $fsb,
								'merg_raschet' => $merg,
								'cur_price' => $needed,
								'cur_min_prof' => $this->minprof,
								'status' => 'stay'
								];

								$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
								$in = array(
								"model"	=> "'".$item['MODEL']."'",
								"sale_id"	=> "'".$sale['sale_id']."'",
								"datetime"=> "'".date('Y-m-d H:i:s')."'",
								"body" => "'".$bodyLog."'",
								);
								$fields = implode(",", array_keys($in));
								$values = implode(",",$in);
								$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
								$this->CurDB->query($sql);
								unset($in);
								unset($bodyLog);
								unset($fields);
								unset($values);
								unset($sql);

								if ($sale['sort'] != '0') {
									$this->deleteArr[] = $item['OZON_ID'];
									$this->usesNow[$sale['sale_id']][] = $item['OZON_ID'];
								}
								if (in_array($item['MODEL'], $this->tops)) {
									$this->salesTop[$sale['sale_id']][] = ['fbo' => $item['FBO'], 'model'=>$item['MODEL'], 'sale'=> $sale['name']];
									$this->reportTop[$item['MODEL']] = ['fbo' => $item['FBO'], 'model'=>$item['MODEL'], 'sale'=> $sale['name']];
								}

								if ($fsb <= $needed && $this->minprof <= $merg && $this->minprof <= $merg_perc) {
									if ($res_update == 0) {
										$reason = 'Идеальные условия.<br>Проходим по всем сво-вам: ' . $fsb . ' < ' . $needed . ' и ' . $this->minprof . ' < ' . $merg;
									} else if ( $res_update == 1){
										$reason = '(ПЕРЕРАСЧЕТ С МАРЖЕЙ 350) Идеальные условия.<br>Проходим по всем сво-вам: ' . $fsb . ' < ' . $needed . ' и ' . $this->minprof . ' < ' . $merg;
									} else if ( $res_update == 2){
										$reason = '(ПЕРЕРАСЧЕТ С МАРЖЕЙ '. $this->minprof_perc .') Идеальные условия.<br>Проходим по всем сво-вам: ' . $fsb . ' < ' . $needed . ' и ' . $this->minprof_perc . ' < ' . $merg_perc;
									}
								} else {
									$reason = "Неизвестный случай.";
								}

								if (in_array($item['MODEL'], $this->indivMarkups)) {
									$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
								}

								$this->arLog[$sale['sale_id']]['STAY'][$item['OZON_ID']] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'reason' => $reason,
								];

								$this->check[$item['OZON_ID']] = 1;
							}
						}
					}
					else if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']]) && $this->curSalePrice[$item['MODEL']][$sale['sale_id']] < $needed) {

						$needed = $item['PRICE'] * (1 - intval($sale['perc']) / 100);

						if (!empty($sale['skd'])) {
							if (isset($this->checkFBOPrice[$item['MODEL']]) && !empty($sale['skd_fbo'])) {
								$newCom = (floatval($this->com) - $sale['skd'] - $sale['skd_fbo']) / 100;
							} else {
								$newCom = (floatval($this->com) - $sale['skd']) / 100;
							}

							$com = floatval($this->com) / 100;
							$merg = ($needed - $needed * $newCom) - floatval($item['SEBES']);
							$merg_perc = ($needed * ( 1 - $newCom ) - $item['SEBES']) / $item['SEBES'] * 100; // Маржа в процентах

							if ( isset($sale['perc']) && !empty($sale['perc']) ) {
								$fsb = $item['PRICE'] * (1 - intval($sale['perc']) / 100);
							} else {
								$fsb = $item['PRICE'];
							}
							$skdSebes = ($fsb * (1 - $com)) / (1 - $newCom);

							// $merg_perc - маржа в процентах
							// $merg - маржа плоская
							// $this->minprof - минимальная плоская маржа в рублях
							// $this->min_prof_perc - минимальная маржа в процентах

							if ($skdSebes > $needed or $this->minprof > $merg or $this->minprof_perc > $merg_perc ) {
								$this->arTmpResU[$sale['sale_id']]['BAD'][$item['OZON_ID']] = [
									'ozon_id' => $item['OZON_ID'],
									'model' => $item['MODEL'],
									'sebes' => $item['SEBES'],
									'price' => $item['PRICE'],
									'needed' => $needed,
									'perc' => floatval($sale['perc']),
									'skdSebes' => $skdSebes,
									'com' => floatval($this->com),
									'newCom' => floatval($this->com) - $sale['skd'],
									'merg' => $merg,
									'min_profit' => $this->minprof,
								];

								if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
									$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
								} else {
									$curSaleprice = 'отсутствует';
								}

								$bodyLog = [
									'ozon_answer' => $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['arr'],
									'sale' => $sale['name'],
									'fbo' => $item['FBO'],
									'sebes' => $item['SEBES'],
									'cur_sale_price' => $curSaleprice,
									'price_raschet' => $skdSebes,
									'merg_raschet' => $merg,
									'cur_price' => $needed,
									'cur_min_prof' => $this->minprof,
									'status' => 'delete'
								];

								$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
								$in = array(
									"model"	=> "'".$item['MODEL']."'",
									"sale_id"	=> "'".$sale['sale_id']."'",
									"datetime"=> "'".date('Y-m-d H:i:s')."'",
									"body" => "'".$bodyLog."'",
								);
								$fields = implode(",", array_keys($in));
								$values = implode(",",$in);
								$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
								$this->CurDB->query($sql);
								unset($in);
								unset($bodyLog);
								unset($fields);
								unset($values);
								unset($sql);

								if ($skdSebes > $needed) {
									$reason = 'Выпадаем из акции. Не проходим по мин. цене: ' . $skdSebes . ' > ' . $needed . ' (расчет с учетом уменьшенной коммиссии на ' . $sale['skd'] . ')';
								} else if ($this->minprof > $merg) {
									$reason = 'Выпадаем из акции. Не проходим по минимально маржинальности: ' . $this->minprof . ' > ' . $merg . ' (расчет с учетом уменьшенной коммиссии на ' . $sale['skd'] . ')';
								} else {
									$reason = "Ошибка неизвестна.";
								}
								if (in_array($item['MODEL'], $this->indivMarkups)) {
									$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
								}
								$this->arLog[$sale['sale_id']]['DELETE'][$item['OZON_ID']] = [
									'ozon_id' => $item['OZON_ID'],
									'model' => $item['MODEL'],
									'reason' => $reason,
								];
							} else {
								$needed_ed = $needed;
								$status_ed = 'stay-with-up';
								if ($needed > $item['PRICE']) {
									$needed = $item['PRICE'];
									$status_ed = 'stay-with-rrc';
								}
								$this->arTmpResU[$sale['sale_id']]['REP'][$item['OZON_ID']] = [
									'ozon_id' => $item['OZON_ID'],
									'model' => $item['MODEL'],
									'sebes' => $item['SEBES'],
									'price' => $item['PRICE'],
									'needed' => $needed,
									'perc' => floatval($sale['perc']),
									'skdSebes' => $skdSebes,
									'com' => floatval($this->com),
									'newCom' => floatval($this->com) - $sale['skd'],
									'merg' => $merg,
									'min_profit' => $this->minprof,
								];
								$this->forUpdatePrice[] = [
									'sale_id' => $sale['sale_id'],
									'model' => $item['MODEL'],
									'price' => $needed,
								];

								$this->info[$item['MODEL']][$sale['sale_id']] = $needed;

								if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
									$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
								} else {
									$curSaleprice = 'отсутствует';
								}

								$bodyLog = [
									'ozon_answer' => $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['arr'],
									'sale' => $sale['name'],
									'fbo' => $item['FBO'],
									'sebes' => $item['SEBES'],
									'cur_sale_price' => $curSaleprice,
									'price_raschet' => $skdSebes,
									'merg_raschet' => $merg,
									'cur_price' => $needed_ed,
									'cur_min_prof' => $this->minprof,
									'status' => $status_ed
								];

								$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
								$in = array(
									"model"	=> "'".$item['MODEL']."'",
									"sale_id"	=> "'".$sale['sale_id']."'",
									"datetime"=> "'".date('Y-m-d H:i:s')."'",
									"body" => "'".$bodyLog."'",
								);
								$fields = implode(",", array_keys($in));
								$values = implode(",",$in);
								$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
								$this->CurDB->query($sql);
								unset($in);
								unset($bodyLog);
								unset($fields);
								unset($values);
								unset($sql);

								if ($sale['sort'] != '0') {
									$this->deleteArr[] = $item['OZON_ID'];
									$this->usesNow[$sale['sale_id']][] = $item['OZON_ID'];
								}
								if (in_array($item['MODEL'], $this->tops)) {
									$this->salesTop[$sale['sale_id']][] = ['fbo' => $item['FBO'], 'model'=>$item['MODEL'], 'sale'=> $sale['name']];
									$this->reportTop[$item['MODEL']] = ['fbo' => $item['FBO'], 'model'=>$item['MODEL'], 'sale'=> $sale['name']];
								}

								if ($skdSebes <= $needed && $this->minprof <= $merg) {
									$reason = 'Повышаем текущую цену акции.<br>Проходим по всем сво-вам: ' . $skdSebes . ' <= ' . $needed . ' и ' . $this->minprof . ' <= ' . $merg . ' (расчет с учетом уменьшенной коммиссии на ' . $sale['skd'] . ')';
								} else {
									$reason = "Неизвестный случай.";
								}

								if (in_array($item['MODEL'], $this->indivMarkups)) {
									$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
								}

								$this->arLog[$sale['sale_id']]['STAY-UP'][$item['OZON_ID']] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'reason' => $reason,
								];

								$this->check[$item['OZON_ID']] = 1;
							}
						}
						else {
							$com = floatval($this->com) / 100;
							$merg = ($needed - $needed * $com) - floatval($item['SEBES']);
							$merg_perc = ($needed * ( 1 - $com ) - floatval($item['SEBES'])) / floatval($item['SEBES']) * 100; // Маржа в процентах

							if ( isset($sale['perc']) && !empty($sale['perc']) ) {
								$fsb = $item['PRICE'] * (1 - intval($sale['perc']) / 100);
							} else {
								$fsb = $item['PRICE'];
							}

							if (isset($this->checkFBOPrice[$item['MODEL']]) && !empty($sale['skd_fbo'])) {
								$newCom = (floatval($this->com) - $sale['skd_fbo']) / 100;
								$fsb = ($fsb * (1 - $com)) / (1 - $newCom);
							}

							//ЛАСТ КОСТЫЛЬ
							$res_update = 0;
							//if ($sale['sale_id'] == '1569653') {

							if ($fsb <= $needed && $this->minprof > $merg) {
								$newPrice = ($this->minprof + $item['SEBES']) / (1 - $com);
								if ($newPrice <= $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['need_price']) {
									$needed = $newPrice;
									$fsb = $newPrice;
									$merg = $this->minprof;
									$res_update = 1;
								}
							}
							else if ( $this->minprof_perc > $merg_perc ) {
								$newPrice = ( (1 + $this->minprof_perc / 100 ) * $item['SEBES'] ) / ( 1 - $com );
								// $newPrice = ($this->minprof + $item['SEBES']) / (1 - $com);
								if ( $newPrice <= $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['need_price'] ) {
									$needed = $newPrice;
									$fsb = $newPrice;
									$merg = $this->minprof;
									$merg_perc = $this->minprof_perc;
									$res_update = 2;
								}
							}
							//}


							if ($fsb > $needed or $this->minprof > $merg or $this->minprof_perc > $merg_perc) {
								$this->arTmpResU[$sale['sale_id']]['BAD'][$item['OZON_ID']] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'sebes' => $item['SEBES'],
								'price' => $itme['PRICE'],
								'needed' => $needed,
								'perc' => $sale['perc'],
								'com' => $this->com,
								'merg' => $merg,
								'min_profit' => $this->minprof,
								];

								if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
									$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
								} else {
									$curSaleprice = 'отсутствует';
								}

								$bodyLog = [
								'ozon_answer' => $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['arr'],
								'sale' => $sale['name'],
								'fbo' => $item['FBO'],
								'sebes' => $item['SEBES'],
								'cur_sale_price' => $curSaleprice,
								'price_raschet' => $fsb,
								'merg_raschet' => $merg,
								'cur_price' => $needed,
								'cur_min_prof' => $this->minprof,
								'status' => 'delete'
								];

								$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
								$in = array(
								"model"	=> "'".$item['MODEL']."'",
								"sale_id"	=> "'".$sale['sale_id']."'",
								"datetime"=> "'".date('Y-m-d H:i:s')."'",
								"body" => "'".$bodyLog."'",
								);
								$fields = implode(",", array_keys($in));
								$values = implode(",",$in);
								$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
								$this->CurDB->query($sql);
								unset($in);
								unset($bodyLog);
								unset($fields);
								unset($values);
								unset($sql);

								if ($fsb  > $needed) {
									$reason = 'Выпадаем из акции. Не проходим по мин. цене: ' . $fsb . ' > ' . $needed . '';
								} else if ($this->minprof > $merg) {
									$reason = 'Выпадаем из акции. Не проходим по минимально маржинальности: ' . $this->minprof . ' > ' . $merg . '';
								} else if ($this->minprof_perc > $merg_perc) {
									$reason = 'Выпадаем из акции. Не проходим по минимальной процентной маржинальности: ' . $this->minprof_perc . ' > ' . $merg_perc . '';
								} else {
									$reason = "Ошибка неизвестна.";
								}

								if (in_array($item['MODEL'], $this->indivMarkups)) {
									$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
								}

								$this->arLog[$sale['sale_id']]['DELETE'][$item['OZON_ID']] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'reason' => $reason,
								];
							}
							else {
								$needed_ed = $needed;
								$status_ed = 'stay-with-up';
								if ($needed > $item['PRICE']) {
									$needed = $item['PRICE'];
									$status_ed = 'stay-with-rrc';
								}
								$this->arTmpResU[$sale['sale_id']]['REP'][$item['OZON_ID']] = [
									'ozon_id' => $item['OZON_ID'],
									'model' => $item['MODEL'],
									'sebes' => $item['SEBES'],
									'price' => $fsb,
									'needed' => $needed,
									'perc' => $sale['perc'],
									'com' => $this->com,
									'merg' => $merg,
									'min_profit' => $this->minprof,
								];
								$this->forUpdatePrice[] = [
									'sale_id' => $sale['sale_id'],
									'model' => $item['MODEL'],
									'price' => $needed,
								];
								$this->info[$item['MODEL']][$sale['sale_id']] = $needed;
								if ($sale['sort'] != '0') {
									$this->deleteArr[] = $item['OZON_ID'];
									$this->usesNow[$sale['sale_id']][] = $item['OZON_ID'];
								}
								if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
									$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
								} else {
									$curSaleprice = 'отсутствует';
								}

								$bodyLog = [
									'ozon_answer' => $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['arr'],
									'sale' => $sale['name'],
									'fbo' => $item['FBO'],
									'sebes' => $item['SEBES'],
									'cur_sale_price' => $curSaleprice,
									'price_raschet' => $fsb,
									'merg_raschet' => $merg,
									'cur_price' => $needed_ed,
									'cur_min_prof' => $this->minprof,
									'status' => $status_ed
								];

								$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
								$in = array(
									"model"	=> "'".$item['MODEL']."'",
									"sale_id"	=> "'".$sale['sale_id']."'",
									"datetime"=> "'".date('Y-m-d H:i:s')."'",
									"body" => "'".$bodyLog."'",
								);
								$fields = implode(",", array_keys($in));
								$values = implode(",",$in);
								$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
								$this->CurDB->query($sql);
								unset($in);
								unset($bodyLog);
								unset($fields);
								unset($values);
								unset($sql);

								if (in_array($item['MODEL'], $this->tops)) {
									$this->salesTop[$sale['sale_id']][] = ['fbo' => $item['FBO'], 'model'=>$item['MODEL'], 'sale'=> $sale['name']];
									$this->reportTop[$item['MODEL']] = ['fbo' => $item['FBO'], 'model'=>$item['MODEL'], 'sale'=> $sale['name']];
								}

								if ($fsb <= $needed && $this->minprof <= $merg) {
									if ($res_update == 0) {
										$reason = 'Повышаем текущую цену акции.<br>Проходим по всем сво-вам: ' . $fsb . ' < ' . $needed . ' и ' . $this->minprof . ' < ' . $merg . '';
									} else {
										$reason = '(ПЕРЕСЧЕТ С УЧЕТОМ МАРЖИ = 350)Повышаем текущую цену акции.<br>Проходим по всем сво-вам: ' . $fsb . ' < ' . $needed . ' и ' . $this->minprof . ' < ' . $merg . '';
									}
								} else {
									$reason = "Неизвестный случай.";
								}

								if (in_array($item['MODEL'], $this->indivMarkups)) {
									$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
								}

								$this->arLog[$sale['sale_id']]['STAY-UP'][$item['OZON_ID']] = [
									'ozon_id' => $item['OZON_ID'],
									'model' => $item['MODEL'],
									'reason' => $reason,
								];

								$this->check[$item['OZON_ID']] = 1;
							}
						}
					}
				}
			}
			foreach ($this->items as $key => &$item) {
				//UNSET CHECK
				if (in_array($item['OZON_ID'],$this->deleteArr) && !in_array($item['OZON_ID'],$this->usesNow[$sale['sale_id']])) {
					$this->arTmpRes[$sale['sale_id']]['BAD'][] = [
						'ozon_id' => $item['OZON_ID'],
						'model' => $item['MODEL'],
						'sebes' => '',
						'price' => '',
						'needed' => '',
						'perc' => '',
						'skdSebes' => '',
						'com' => '',
						'newCom' => '',
						'merg' => '$merg',
						'min_profit' => '',
					];


					if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
						$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
					} else {
						$curSaleprice = 'отсутствует';
					}


					$bodyLog = [
						'ozon_answer' => $this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]['arr'],
						'sale' => $sale['name'],
						'fbo' => $item['FBO'],
						'sebes' => $item['SEBES'],
						'cur_sale_price' => $curSaleprice,
						'price_raschet' => $skdSebes,
						'merg_raschet' => $merg,
						'cur_price' => $needed,
						'cur_min_prof' => $this->minprof,
						'status' => 'not-add-prioretet'
					];

					$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
					$in = array(
						"model"	=> "'".$item['MODEL']."'",
						"sale_id"	=> "'".$sale['sale_id']."'",
						"datetime"=> "'".date('Y-m-d H:i:s')."'",
						"body" => "'".$bodyLog."'",
					);
					$fields = implode(",", array_keys($in));
					$values = implode(",",$in);
					$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
					$this->CurDB->query($sql);
					unset($in);
					unset($bodyLog);
					unset($fields);
					unset($values);
					unset($sql);

					$reason = 'Товар не добавлен в акцию, т.к. вошел в другую акцию по приоритету';

					$this->arLog[$sale['sale_id']]['NOT_ADD'][$item['OZON_ID']] = [
						'ozon_id' => $item['OZON_ID'],
						'model' => $item['MODEL'],
						'reason' => $reason,
					];

					continue;
				}

				if (in_array($item['BRAND'],$sale['brand_unset'])) {

					$this->arTmpRes[$sale['sale_id']]['BAD'][] = [
					'ozon_id' => $item['OZON_ID'],
					'model' => $item['MODEL'],
					'sebes' => '',
					'price' => '',
					'needed' => '',
					'perc' => '',
					'skdSebes' => '',
					'com' => '',
					'newCom' => '',
					'merg' => '$merg',
					'min_profit' => '',
					];
					$reason = 'Бренд исключен!';
				}
				else	if (isset($this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]) && !empty($item['PRICE']) && !isset($this->arTmpResU[$sale['sale_id']]['GOOD'][$item['OZON_ID']])) {
					$needed = $this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]['need_price'];

					$needed = $item['PRICE'] * (1 - intval($sale['perc']) / 100);

					if ($this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]['need_price'] < $needed) {
						$this->arTmpRes[$sale['sale_id']]['BAD'][] = [
							'ozon_id' => $item['OZON_ID'],
							'model' => $item['MODEL'],
							'sebes' => 	$item['SEBES'],
							'price' => $item['PRICES'],
							'needed' => $needed,
							'perc' => floatval($sale['perc']),
							'skdSebes' => '',
							'com' => floatval($this->com),
							'newCom' => floatval($this->com) - floatval($sale['skd']),
							'merg' => '',
							'min_profit' => $this->minprof,
						];

						if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
							$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
						} else {
							$curSaleprice = 'отсутствует';
						}


						$bodyLog = [
							'ozon_answer' => $this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]['arr'],
							'sale' => $sale['name'],
							'fbo' => $item['FBO'],
							'sebes' => $item['SEBES'],
							'cur_sale_price' => $curSaleprice,
							'price_raschet' => $needed,
							'merg_raschet' => $merg,
							'cur_price' => $this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]['need_price'],
							'cur_min_prof' => $this->minprof,
							'status' => 'not-add'
						];

						$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
						$in = array(
							"model"	=> "'".$item['MODEL']."'",
							"sale_id"	=> "'".$sale['sale_id']."'",
							"datetime"=> "'".date('Y-m-d H:i:s')."'",
							"body" => "'".$bodyLog."'",
						);
						$fields = implode(",", array_keys($in));
						$values = implode(",",$in);
						$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
						$this->CurDB->query($sql);
						unset($in);
						unset($bodyLog);
						unset($fields);
						unset($values);
						unset($sql);

						$reason = "Не проходим по мин. цене: ".$this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]['need_price']." < ".$needed."";


						$this->arLog[$sale['sale_id']]['NOT_ADD'][$item['OZON_ID']] = [
						'ozon_id' => $item['OZON_ID'],
						'model' => $item['MODEL'],
						'reason' => $reason,
						];
						continue;
					}

					if ( !empty($sale['skd']) ) {
						if (isset($this->checkFBOPrice[$item['MODEL']]) && !empty($sale['skd_fbo'])) {
							$newCom = (floatval($this->com) - $sale['skd'] - $sale['skd_fbo']) / 100;
						}else{
							$newCom = (floatval($this->com) - $sale['skd']) / 100;
						}

						$com = floatval($this->com) / 100;
						$merg = ($needed - $needed * $newCom) -floatval($item['SEBES']);
						$merg_perc = ($needed * ( 1 - $newCom ) - floatval($item['SEBES'])) / floatval($item['SEBES']) * 100; // Маржа в процентах

						if ( isset($sale['perc']) && !empty($sale['perc']) ) {
							$fsb = $item['PRICE'] * (1 - intval($sale['perc'])/100);
						} else {
							$fsb = $item['PRICE'];
						}
						$skdSebes = ($fsb * (1 - $com)) / (1 - $newCom);
						if ($skdSebes > $needed or $this->minprof > $merg or $this->minprof_perc > $merg_perc ) {
							$this->arTmpRes[$sale['sale_id']]['BAD'][] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'sebes' => 	$item['SEBES'],
								'price' => $item['PRICES'],
								'needed' => $needed,
								'perc' => floatval($sale['perc']),
								'skdSebes' => $skdSebes,
								'com' => floatval($this->com),
								'newCom' => floatval($this->com) - $sale['skd'],
								'merg' => $merg,
								'min_profit' => $this->minprof,
							];

							if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
								$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
							} else {
								$curSaleprice = 'отсутствует';
							}


							$bodyLog = [
								'ozon_answer' => $this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]['arr'],
								'sale' => $sale['name'],
								'fbo' => $item['FBO'],
								'sebes' => $item['SEBES'],
								'cur_sale_price' => $curSaleprice,
								'price_raschet' => $skdSebes,
								'merg_raschet' => $merg,
								'cur_price' => $needed,
								'cur_min_prof' => $this->minprof,
								'status' => 'not-add'
							];

							$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
							$in = array(
								"model"	=> "'".$item['MODEL']."'",
								"sale_id"	=> "'".$sale['sale_id']."'",
								"datetime"=> "'".date('Y-m-d H:i:s')."'",
								"body" => "'".$bodyLog."'",
							);
							$fields = implode(",", array_keys($in));
							$values = implode(",",$in);
							$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
							$this->CurDB->query($sql);
							unset($in);
							unset($bodyLog);
							unset($fields);
							unset($values);
							unset($sql);

							if ($skdSebes > $needed) {
								$reason = 'Не проходим по мин. цене: '.$skdSebes .' > '.$needed.' (расчет с учетом уменьшенной коммиссии на '.$sale['skd'].')';
							} else if ($this->minprof > $merg) {
								$reason = 'Не проходим по минимально маржинальности: '.$this->minprof  .' > '.$merg.' (расчет с учетом уменьшенной коммиссии на '.$sale['skd'].')';
							} else if ($this->minprof_perc > $merg_perc) {
								$reason = 'Не проходим по минимальной процентной маржинальности: '.$this->minprof_perc  .' > '.$merg_perc.' (расчет с учетом уменьшенной коммиссии на '.$sale['skd'].')';
							} else {
								$reason = "Ошибка неизвестна.";
							}

							if ( in_array($item['MODEL'], $this->indivMarkups) ) {
								$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
							}


							$this->arLog[$sale['sale_id']]['NOT_ADD'][$item['OZON_ID']] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'reason' => $reason,
							];
						} else {
							if ($needed > $item['PRICE']) {
								$needed = $item['PRICE'];
							}

							$this->arTmpRes[$sale['sale_id']]['GOOD'][] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'sebes' => $item['SEBES'],
								'price' => $item['PRICES'],
								'needed' => $needed,
								'perc' => floatval($sale['perc']),
								'skdSebes' => $skdSebes,
								'com' => floatval($this->com),
								'newCom' => floatval($this->com) - $sale['skd'],
								'merg' => $merg,
								'min_profit' => $this->minprof,
							];

							$this->info[$item['MODEL']][$sale['sale_id']] = $needed;

							if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
								$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
							} else {
								$curSaleprice = 'отсутствует';
							}
							if ($sale['sort'] != '0') {
								$this->deleteArr[] = $item['OZON_ID'];
								$this->usesNow[$sale['sale_id']][] = $item['OZON_ID'];
							}
							$bodyLog = [
								'ozon_answer' => $this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]['arr'],
								'sale' => $sale['name'],
								'fbo' => $item['FBO'],
								'sebes' => $item['SEBES'],
								'cur_sale_price' => $curSaleprice,
								'price_raschet' => $skdSebes,
								'merg_raschet' => $merg,
								'cur_price' => $needed,
								'cur_min_prof' => $this->minprof,
								'status' => 'add'
							];

							$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
							$in = array(
								"model"	=> "'".$item['MODEL']."'",
								"sale_id"	=> "'".$sale['sale_id']."'",
								"datetime"=> "'".date('Y-m-d H:i:s')."'",
								"body" => "'".$bodyLog."'",
							);
							$fields = implode(",", array_keys($in));
							$values = implode(",",$in);
							$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
							$this->CurDB->query($sql);
							unset($in);
							unset($bodyLog);
							unset($fields);
							unset($values);
							unset($sql);

							$this->forUpdatePrice[] = [
								'sale_id' => $sale['sale_id'],
								'model' => $item['MODEL'],
								'price' => $needed,
							];
							if (in_array($item['MODEL'],$this->tops)) {
								$this->salesTop[$sale['sale_id']][] = ['fbo' => $item['FBO'], 'model'=>$item['MODEL'], 'sale'=> $sale['name']];
								$this->reportTop[$item['MODEL']] = ['fbo' => $item['FBO'], 'model'=>$item['MODEL'], 'sale'=> $sale['name']];
							}

							if ($skdSebes <= $needed && $this->minprof <= $merg) {
								$reason = 'Проходим по всем сво-вам: '.$skdSebes .' <= '.$needed.' и '.$this->minprof.' <= '.$merg.' (расчет с учетом уменьшенной коммиссии на '.$sale['skd'].')';
							} else {
								$reason = "Неизвестный случай.";
							}

							if (in_array($item['MODEL'],$this->indivMarkups)) {
								$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
							}


							$this->arLog[$sale['sale_id']]['ADD'][$item['OZON_ID']] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'reason' => $reason,
							];
							$this->check[$item['OZON_ID']] = 1;
						}
					}
					else {

						$com = floatval($this->com) / 100;
						$merg = ($needed - $needed * $com) - floatval($item['SEBES']);
						$merg_perc = ($needed * ( 1 - $com ) - floatval($item['SEBES'])) / floatval($item['SEBES']) * 100; // Маржа в процентах

						if ( isset($sale['perc']) && !empty($sale['perc']) ) {
							$fsb = $item['PRICE'] * (1 - intval($sale['perc'])/100);
						} else {
							$fsb = $item['PRICE'];
						}

						if (isset($this->checkFBOPrice[$item['MODEL']]) && !empty($sale['skd_fbo'])) {
							$newCom = (floatval($this->com) - $sale['skd_fbo']) / 100;
							$fsb = ($fsb * (1 - $com)) / (1 - $newCom);
						}

						$res_update = 0;

						if ($fsb <= $needed && $this->minprof > $merg) {
							$newPrice = ($this->minprof + $item['SEBES']) / (1 - $com);
							if ($newPrice <= $this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]['need_price']) {

								$needed = $newPrice;
								$fsb = $newPrice;
								$merg = $this->minprof;
								$res_update = 1;
							}
						}
						else if ( $this->minprof_perc > $merg_perc ) {
							$newPrice = ( (1 + $this->minprof_perc / 100 ) * $item['SEBES'] ) / ( 1 - $com );
							// $newPrice = ($this->minprof + $item['SEBES']) / (1 - $com);
							if ( $newPrice <= $this->tmpPorductsM[$sale['sale_id']]['USES'][$item['OZON_ID']]['need_price'] ) {
								$needed = $newPrice;
								$fsb = $newPrice;
								$merg = $this->minprof;
								$merg_perc = $this->minprof_perc;
								$res_update = 2;
							}
						}

						if ($fsb > $needed or $this->minprof > $merg or $this->minprof_perc > $merg_perc ) {
							$this->arTmpRes[$sale['sale_id']]['BAD'][] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'sebes' => $item['SEBES'],
								'price' => $fsb,
								'needed' => $needed,
								'perc' => floatval($sale['perc']),
								'com' => floatval($this->com),
								'merg' => $merg,
								'min_profit' => $this->minprof,
							];

							if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
								$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
							} else {
								$curSaleprice = 'отсутствует';
							}

							$bodyLog = [
								'ozon_answer' => $this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]['arr'],
								'sale' => $sale['name'],
								'fbo' => $item['FBO'],
								'sebes' => $item['SEBES'],
								'cur_sale_price' => $curSaleprice,
								'price_raschet' => $fsb,
								'merg_raschet' => $merg,
								'cur_price' => $needed,
								'cur_min_prof' => $this->minprof,
								'status' => 'not-add'
							];

							$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
							$in = array(
								"model"	=> "'".$item['MODEL']."'",
								"sale_id"	=> "'".$sale['sale_id']."'",
								"datetime"=> "'".date('Y-m-d H:i:s')."'",
								"body" => "'".$bodyLog."'",
							);
							$fields = implode(",", array_keys($in));
							$values = implode(",",$in);
							$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
							$this->CurDB->query($sql);
							unset($in);
							unset($bodyLog);
							unset($fields);
							unset($values);
							unset($sql);

							if ($fsb > $needed) {
								$reason = 'Не проходим по мин. цене: '. $fsb .' > '.$needed.'';
							} else if ($this->minprof > $merg) {
								$reason = 'Не проходим по минимально маржинальности: '.$this->minprof  .' > '.$merg.'';
							} else if ($this->minprof_perc > $merg_perc) {
								$reason = 'Не проходим по минимальной процентной маржинальности: '.$this->minprof_perc  .' > '.$merg_perc.'';
							} else {
								$reason = "Ошибка неизвестна.";
							}

							if (in_array($item['MODEL'],$this->indivMarkups)) {
								$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
							}


							$this->arLog[$sale['sale_id']]['NOT_ADD'][$item['OZON_ID']] = [
							'ozon_id' => $item['OZON_ID'],
							'model' => $item['MODEL'],
							'reason' => $reason,
							];
						}
						else {
							if ($needed > $item['PRICE']) {
								$needed = $item['PRICE'];
							}
							$this->arTmpRes[$sale['sale_id']]['GOOD'][] = [
								'ozon_id' => $item['OZON_ID'],
								'model' => $item['MODEL'],
								'sebes' => $item['SEBES'],
								'price' => $fsb,
								'needed' => $needed,
								'perc' => floatval($sale['perc']),
								'com' => floatval($this->com),
								'merg' => $merg,
								'min_profit' => $this->minprof,
							];

							$this->info[$item['MODEL']][$sale['sale_id']] = $needed;

							if (isset($this->curSalePrice[$item['MODEL']][$sale['sale_id']])) {
								$curSaleprice = $this->curSalePrice[$item['MODEL']][$sale['sale_id']];
							} else {
								$curSaleprice = 'отсутствует';
							}

							$bodyLog = [
								'ozon_answer' => $this->tmpPorducts[$sale['sale_id']]['CAN'][$item['OZON_ID']]['arr'],
								'sale' => $sale['name'],
								'fbo' => $item['FBO'],
								'sebes' => $item['SEBES'],
								'cur_sale_price' => $curSaleprice,
								'price_raschet' => $fsb,
								'merg_raschet' => $merg,
								'cur_price' => $needed,
								'cur_min_prof' => $this->minprof,
								'status' => 'add'
							];

							$bodyLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
							$in = array(
								"model"	=> "'".$item['MODEL']."'",
								"sale_id"	=> "'".$sale['sale_id']."'",
								"datetime"=> "'".date('Y-m-d H:i:s')."'",
								"body" => "'".$bodyLog."'",
							);
							$fields = implode(",", array_keys($in));
							$values = implode(",",$in);
							$sql = "INSERT INTO ozon_sales_log_{$this->cabinet} ($fields) VALUES ($values)";
							$this->CurDB->query($sql);
							unset($in);
							unset($bodyLog);
							unset($fields);
							unset($values);
							unset($sql);

							if ($sale['sort'] != '0') {
								$this->deleteArr[] = $item['OZON_ID'];
								$this->usesNow[$sale['sale_id']][] = $item['OZON_ID'];
							}
							$this->forUpdatePrice[] = [
								'sale_id' => $sale['sale_id'],
								'model' => $item['MODEL'],
								'price' => $needed,
							];
							if (in_array($item['MODEL'],$this->tops)) {
								$this->salesTop[$sale['sale_id']][] = ['fbo' => $item['FBO'], 'model' => $item['MODEL'], 'sale'=> $sale['name']];
								$this->reportTop[$item['MODEL']] = ['fbo' => $item['FBO'], 'model'=>$item['MODEL'], 'sale'=> $sale['name']];
							}

							if ($fsb <= $needed && $this->minprof <= $merg) {
								if ($res_update == 0) {
									$reason = 'Проходим по всем сво-вам: '.$fsb.' <= '.$needed.' и '.$this->minprof.' <= '.$merg.'';
								} else if ( $res_update == 1) {
									$reason = '(ПЕРЕСЧЕТ С МОРЖНОЙ 350) Проходим по всем сво-вам: '.$fsb.' <= '.$needed.' и '.$this->minprof.' <= '.$merg.'';
								} else if ( $res_update == 2) {
									$reason = '(ПЕРЕСЧЕТ С МАРЖЯЙ '.$this->minprof_perc.') Проходим по всем сво-вам: '.$fsb.' <= '.$needed.' и '.$this->minprof_perc.' <= '.$merg_perc.'';
								}
							} else {
								$reason = "Неизвестный случай.";
							}

							if (in_array($item['MODEL'],$this->indivMarkups)) {
								$reason .= '<br><b>ПРИ ПОДСЧЕТЕ РРЦ ИСПОЛЬЗОВАЛАСЬ ИНДИВИДУАЛЬНАЯ НАЦЕНКА</b>';
							}


							$this->arLog[$sale['sale_id']]['ADD'][$item['OZON_ID']] = [
							'ozon_id' => $item['OZON_ID'],
							'model' => $item['MODEL'],
							'reason' => $reason,
							];
							$this->check[$item['OZON_ID']] = 1;
						}
					}
				}
			}
		}
		file_put_contents(
			"/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$this->cabinet}/sales/log_2.txt",
			print_r(json_encode($this->arTmpResU), true)
		);
	}

	public function UsesReplace()
	{
		if ( empty($this->salesActive) ) return;

		foreach ($this->salesActive as $k => $sale) {
			if ( empty($this->arTmpResU[$sale['sale_id']]['REP']) ) continue;

			$tws = array_chunk($this->arTmpResU[$sale['sale_id']]['REP'], 1000);
			foreach ($tws as $key => $values) {
				foreach ($values as $key => $v) {
					$tmpPrd[] = ['action_price' => $v['needed'], 'product_id'=> $v['ozon_id']];
				}
				$data_string = json_encode([
					'action_id' => $sale['sale_id'],
					'products' => $tmpPrd
				]);

				$res = $this->request(
					url: $this->api_url . '/v1/actions/products/activate',
					headers: $this->getHeaders(),
					data: $data_string,
				);

				unset($tmpPrd);
			}
		}
	}

	public function DeactivateSales()
	{
		if ( empty($this->salesActive) ) return;

		foreach ($this->salesActive as $k => $sale) {
			if ( empty($this->arTmpResU[$sale['sale_id']]['BAD']) ) continue;

			$tws = array_chunk($this->arTmpResU[$sale['sale_id']]['BAD'],1000);
			foreach ($tws as $key => $values) {
				foreach ($values as $key => $v) {
					$tmpPrd[] = $v['ozon_id'];
				}
				$data_string = json_encode([
					'action_id' => $sale['sale_id'],
					'product_ids' => $tmpPrd
				]);

				$res = $this->request(
					url: $this->api_url . '/v1/actions/products/deactivate',
					headers: $this->getHeaders(),
					data: $data_string,
				);
				unset($tmpPrd);
			}
		}
	}

	public function ActivateSales()
	{
		if ( empty($this->salesActive) ) return;

		foreach ($this->salesActive as $k => $sale) {
			$tws = array_chunk( $this->arTmpRes[$sale['sale_id']]['GOOD'], 1000 );
			foreach ($tws as $key => $values) {
				foreach ($values as $key => $v) {
					$tmpPrd[] = ['action_price' => $v['needed'], 'product_id'=> $v['ozon_id']];
				}
				$data_string = json_encode([
					'action_id' => $sale['sale_id'],
					'products' => $tmpPrd
				]);

				$res = $this->request(
					url: $this->api_url . '/v1/actions/products/activate',
					headers: $this->getHeaders(),
					data: $data_string,
				);
				unset($tmpPrd);
			}
		}
	}

	public function DeleteNotActiveSales()
	{
		foreach ($this->NotActiveSales as $key => $value) {
			$saleID = $value['id'];
			$i = 0;
			$check = true;
			$last_id = "";
			while ($check == true) {
				$data_string = json_encode([
					"action_id" => $saleID,
					"limit" => 1000,
					"last_id" => $last_id
				]);

				$res = $this->request(
					url: $this->api_url . '/v1/actions/products',
					headers: $this->getHeaders(),
					data: $data_string,
				);

				if (isset($res['result'])) {
					if (count($res['result']['products']) < 1000) {
						$check = false;
					} else {
						$check = true;
					}
				} else {
					$check = false;
					print_r("ОШИБКА В ОТВЕТЕ API OZON\n");
					print_r( $res );
					print_r( PHP_EOL );
					print_r( "Sale ID: {$saleID}");

					file_put_contents(
						'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/sales_errors/last_sale_error_DNAS.log',
						print_r($res, true)
					);

					$triggers = new TsTriggers();
					$triggers->SetError(["ОШИБКА ПРИ ОБНОВЛЕНИИ АКЦИЙ [". date('d.m.Y H:i:s')."]\n Метод: DeleteNotActiveSales"]);
					$triggers->SendTriggerErrors();
					die();
				}
				foreach ($res['result']['products'] as $key => $value) {
					$delete[$value['id']] = [
						'product_id' => $value['id'],
						'need_price' => $value['max_action_price'],
					];
				}
				$last_id = $res["result"]["last_id"];
				$i=$i+1000;
				sleep( 5 );
			}
			if ( !empty($delete) ) {
				$tws = array_chunk($delete,1000);
				foreach ($tws as $key => $values) {
					foreach ($values as $key => $v) {
						$tmpPrd[] = $v['product_id'];
					}
					$data_string = json_encode(array('action_id'=>$saleID ,'product_ids'=>$tmpPrd));

					$res = $this->request(
						url: $this->api_url . '/v1/actions/products/deactivate',
						headers: $this->getHeaders(),
						data: $data_string,
					);
				}
			}
		}

		foreach ($this->NotActiveProduct as $key => $value) {
			$saleID = $key;
			$i = 0;
			$check = true;
			$tws = array_chunk($value, 1000);

			foreach ($tws as $key => $values) {
				foreach ($values as $key => $v) {
					$tmpPrd[] = $v['product_id'];
				}
				$data_string = json_encode([
					'action_id' => $saleID,
					'product_ids' => $tmpPrd
				]);

				$res = $this->request(
					url: $this->api_url . '/v1/actions/products/deactivate',
					headers: $this->getHeaders(),
					data: $data_string,
				);

				unset($tmpPrd);
			}
		}
	}

	public function updateSales()
	{
		$res = $this->request(
			url: $this->api_url . '/v1/actions',
			headers: $this->getHeaders(),
			data: false,
		);

		foreach ($res['result'] as $key => $value){
			if ( !isset($this->salesActiveNotSort[$value['id']]) ) continue;

			if ( !empty($this->salesTop[$value['id']]) ) {
				$tops = count($this->salesTop[$value['id']]);
			} else {
				$tops = 0;
			}

			$is_sale_active = $value['is_participating'] ? 1 : 0;

			$ins = array(
				'active' => "'".$is_sale_active."'",
				'potencial' => "'".$value['potential_products_count']."'",
				'uses' => "'".$value['participating_products_count']."'",
				'top_models' => "'".$tops."'",
			);

			$update = [];
			foreach ($ins as $key => $v) {
				$update[] = "$key = $v";
			}
			$update = implode(", ", $update);

			$result = $this->CurDB->query("UPDATE ozon_sales_{$this->cabinet} SET {$update} WHERE sale_id = {$value['id']}");
		}
	}

	public function updatePriceTable()
	{
		$this->CurDB->query("DELETE FROM ozon_sales_prices_{$this->cabinet} WHERE 1=1");

		foreach ($this->forUpdatePrice as $value) {
			$in = array(
				"sale_id" => "'".$value['sale_id']."'",
				"model" => "'".$value['model']."'",
				"price" => "'".$value['price']."'",
			);
			$fields = implode(",", array_keys($in));
	    $values = implode(",",$in);

	    $sql = "INSERT INTO ozon_sales_prices_{$this->cabinet} ($fields) VALUES ($values)";
	    $this->CurDB->query($sql);
		}

		$this->CurDB->query("DELETE FROM ozon_sales_info_{$this->cabinet} WHERE 1=1");
		foreach ($this->info as $model => $sale) {
			foreach ($sale as $sale_id => $v) {
				$in = array(
					"sale_id" => "'".$sale_id."'",
					"model" => "'".$model."'",
					"action_max_price" => "'".$v."'",
				);
				$fields = implode(",", array_keys($in));
		    $values = implode(",",$in);

		    $sql = "INSERT INTO ozon_sales_info_{$this->cabinet} ($fields) VALUES ($values)";
		    $this->CurDB->query($sql);
			}
		}
	}

	private function calculateElasticPrice( array $item, int $saleId ):bool|int
	{
		if ( empty($item) ) return false;
		if ( !isset($item['price_min_elastic']) || !isset($item['price_max_elastic']) ) return false;

		if ( $item['min_boost'] == 0 || $item['max_boost'] == 0 ) return false;
		if ( $item['price_min_elastic'] == $item['price_max_elastic'] ) return false;

		if ( empty($this->salesSettings[$saleId]) || empty($this->salesSettings[$saleId]['boost']) ) return false;

		$minBoost = $item['min_boost'];
		$maxBoost = $item['max_boost'];
		$wishedBoostPercent = intval($this->salesSettings[$saleId]['boost']);

		$priceMin = $item['price_min_elastic'];
		$priceMax = $item['price_max_elastic'];

		$diffPrice = $priceMin - $priceMax;
		$diffBoost = $maxBoost - $minBoost;

		$boostPercent = $diffPrice / $diffBoost;
		$needBoost = $wishedBoostPercent - $minBoost;

		$wishedBoostPrice = $boostPercent * $needBoost;

		$actionPrice = $priceMin - $wishedBoostPrice;

		return intval( round( $actionPrice ) );
	}

	private function getSalesSettings():array
	{
		$rows = $this->CurDB->select(['*'], "ozon_sales_{$this->cabinet}")->make();
		$result = [];

		foreach ( $rows as $row ){
			$result[ $row['sale_id'] ] = $row;
		}

		return $result;
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

	private function request( string $url, array $headers, string|bool $data = false ):array
	{
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

		if ( $data ) curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_HEADER, false );

		$res = curl_exec( $ch );
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close( $ch );

		return json_decode($res, true);
	}

	private function getHeaders():array
	{
		return [
			'Api-Key:' . $this->token,
			'Client-Id:' . $this->client_id,
			'Content-Type:application/json'
		];
	}

}

//(new OzonImportSales())->run();
//(new OzonImportSalesClass())->UpdateTmpTable();
