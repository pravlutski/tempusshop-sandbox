<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
use Bitrix\Main\Application,
	Bitrix\Main\Loader;

CModule::IncludeModule('panel.manager');

class checkFBONEW{
	public function __construct($cabinet){
		if (empty($cabinet) || $cabinet == '') {
			die('need cabinet_id');
		}
		global $DB;
		$this->loadModules();
		$this->cabinet = $cabinet;
		$this->CurDB = new DBPanel();
		$this->db = $DB;

		$this->bot = new TGNotifier;
		$this->control = [];

		$this->module = 'checkFbo_' . $this->cabinet;

		$result = $this->CurDB->query("SELECT * FROM ozon_main_settings_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$arSetting[$row['name']] = $row['value'];
		}
		unset($result);
		unset($rows);

		$result = $this->CurDB->query("SELECT * FROM ozon_fbo_stock_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->checkFBOStock[$row['article']] = "Y";
		}
		unset($result);
		unset($rows);

		$result = $this->CurDB->query("SELECT * FROM ozon_fbo_stock_{$this->cabinet}");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$this->checkFBOStock[$row['article']] = "Y";
		}
		unset($result);
		unset($rows);

		$resDB = $this->CurDB->select(['*'], 'ms_cost_substitution')->where('supplier_id', 144)->make();
		$costSubst = [];
		foreach( $resDB as $row ){
			$costSubst[ $row['model'] ] = $row['price'];
		}

		$modelsImport = $this->getStockImportItems();

    $this->fromMS = array();
		$result = $this->CurDB->query("SELECT * FROM ms_turnover");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
			if ( in_array( $row['model'], $modelsImport ) && !empty( $costSubst[ $row['model'] ] ) ){
				$this->fromMS[$row['model']] = $costSubst[ $row['model'] ];
				continue;
			}
			$this->fromMS[$row['model']] = intval($row['quantity']);
		}
		unset($result);
		unset($rows);

		$this->mcom = $arSetting['com'];
		$this->mnewCom = $arSetting['newCom'];
    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];
		$this->bot_threshold = intval($arSetting['bot_threshold']);
		$this->threshold = intval($arSetting['fbo_threshold']);

		$this->shouldZeroStocks = ($arSetting['should_zero_stocks'] == 'Y') ? true : false; // Будем ли передавать остатки
		$this->stock_threshold = intval( $arSetting['stock_threshold'] ); // Порог разницы себестоимостей для передачи остатков

		$this->excludeStockImportPath = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/configs/excludeStockImport.json";

		$this->sppAnalyticsData = []; // Для почасовой аналитики
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
		$this->FBO_WH = [];
		$timeStart = date('Y.m.d G:i:s');
		$this->arLog['TIME_START'] = $timeStart;
		$date = date('Y-m-d');

    //Агент-Инфо
		$arStat = [
			'status' => 'PROCESS',
			'status_text' => 'Запуск скрипта',
			'percent' => 0,
			'time_start' => $timeStart
		];
		$this->updateStatus($this->module, $arStat);

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение остатков FBO по складам', 'percent' => 10]);

		$startT = microtime(true);
		// $this->getStockFboWH();
		$this->getCostNF();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение товаров из BT', 'percent' => 20]);

		$this->getItems();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение данных от OZON', 'percent' => 30]);

		// $this->getStockFbo();
		$this->getStockFboReport();
		$this->getIndMarkups();
		$this->getSebes();

		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Получение себесов из МС', 'percent' => 40]);

		$this->getCiBrandID();
		//$this->GetTurnover();


		//Агент-Инфо
		$this->updateStatus($this->module, ['status_text' => 'Выполняем проверку ФБО', 'percent' => 50]);

		$this->checkFBOStock();
		try{
			$this->checkStockDifference();
		}
		catch( Throwable $e ){
			file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/checkFboDebug.txt', print_r($e->getMessage(), true));
			var_dump($e);
		}
		$itemsT = microtime(true);
		$this->PrintResult();

		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено',
			'percent' => 100,
			'time_end' => date('Y-m-d G:i:s'),
		];
		$this->updateStatus($this->module, $arStat);
	}

  public function getItems(){
	    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_OZSB_PRICE","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD", "PROPERTY_BRAND");
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

				//print_r($el);
				if (empty($el['PROPERTY_WBARTICLE_VALUE']) or $el['PROPERTY_WBARTICLE_VALUE'] == '') {
						$this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
				}	else if (empty($el['PROPERTY_OZSB_PRICE_VALUE']) or $el['PROPERTY_OZSB_PRICE_VALUE'] == 0) {
						$this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
				} else {
					$arSection = getSectionsElement($el["ID"]);
					// if ($arSection[1]['ID'] == '558') {
		    	$this->items[$el["PROPERTY_WBARTICLE_VALUE"]] = [
		    		"ID" => $el["ID"],
						"ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
		    		"OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_VALUE"],
		    		"PRICE" => $el["PROPERTY_OZSB_PRICE_VALUE"],
						"BRAND_BX" => $el["PROPERTY_BRAND_VALUE"],
		    	];
				}
	    }
			//die();
		}

		public function getStockFboWH() {
			$check = true;
			$lim = 1000;
			$off = 0;
			while ($check) {
				file_put_contents('/var/www/bitrix_logs/ozon/timing.txt', print_r(['getStockFboWH', date('Y-m-d H:i:s')], true), 8);
				$data = [
					"limit" => $lim,
			    "offset" => $off,
			    "warehouse_type" => "ALL"
				];

				$ch = curl_init($this->api_url . '/v2/analytics/stock_on_warehouses');
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
				file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/res_checkFbo_STOCK.txt', print_r($res,1));
				if (!empty($res['result']['rows'])) {

					foreach ($res['result']['rows'] as $key => $value) {
						if ($value['free_to_sell_amount'] > 0) {
							if (strpos(strtolower($value['warehouse_name']), 'екатерин') === false
								|| strpos(strtolower($value['warehouse_name']), 'красноя') === false
								|| strpos(strtolower($value['warehouse_name']), 'новосиб') === false
								|| strpos(strtolower($value['warehouse_name']), 'омск') === false
								|| strpos(strtolower($value['warehouse_name']), 'калининг') === false
								|| strpos(strtolower($value['warehouse_name']), 'хабаров') === false
								|| strpos(strtolower($value['warehouse_name']), 'минск') === false
								|| strpos(strtolower($value['warehouse_name']), 'астан') === false
								|| strpos(strtolower($value['warehouse_name']), 'алмат') === false ) {
								$this->FBO_WH[$value['item_code']][] = $value;
							}
						}
					}
					$off = $off + $lim;
				} else {
					$check = false;
				}
			}

	  }

		private function getCostNF()
		{
			$strSql = "SELECT model, price FROM ci_price WHERE supplier_id = 144";
			$rows = $this->db->Query( $strSql );
			$items = [];

			while ( $row = $rows->Fetch() ){
				$items[$row['model']] = $row['price'];
			}

			$this->stockNFCost = $items;
		}

	  public function getStockFbo() { // Метод в бете. Не работает 09.04.25
			$this->stockFbo = array();
	    foreach ($this->items as $key => &$arItem) {
				$offerIDs[] = $arItem['OZON_ARTICLE'];
	    }
			$offerIDsC = array_chunk($offerIDs, 1000);
			foreach ($offerIDsC as $key => $value) {
				file_put_contents('/var/www/bitrix_logs/ozon/timing.txt', print_r(['getStockFbo', date('Y-m-d H:i:s')], true), 8);
				$data = [
					'limit' => 1000,
					'offset' => 0,
				];

				$ch = curl_init($this->api_url . '/v1/analytics/manage/stocks');
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

				if (!empty($res['items'])) {
					foreach ($res['items'] as $key => $value) {
						if ( empty($this->stockFbo[$value['offer_id']]) ) {
						 $this->stockFbo[$value['offer_id']] = intval($value['valid_stock_count']);
					 }else{
						 $this->stockFbo[$value['offer_id']] += intval($value['valid_stock_count']);
					 }
					}
				} else {
					$timeEnd = date('Y.m.d G:i:s');
					$arStat = [
						'status' => 'COMPLETE',
						'status_text' => 'Завершено',
						'percent' => 100,
						'time_end' => $timeEnd
					];
					$this->updateStatus($this->module, $arStat);
					file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/die_FBO.txt', print_r($res,1));
					die();
				}
			}

	  }

		public function getStockFboReport() {
			$this->stockFbo = array();
			$data = [
				'limit' => 1000,
				'offset' => 0,
				'warehouse_type' => 'ALL',
			];
			$flag = true;
			$try = 0;
			$page = 1;
			$stockReport = [];

			while ( $flag ){
				file_put_contents('/var/www/bitrix_logs/ozon/timing.txt', print_r(['getStockFboReport', date('Y-m-d H:i:s')], true), 8);
				if ( $try == 7 ) $flag == false;
				$ch = curl_init( "https://api-seller.ozon.ru/v2/analytics/stock_on_warehouses" );
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
				file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/res_checkFbo.txt', print_r($res,1));

				curl_close($ch);
				$res = json_decode($res, true);

				file_put_contents(
					'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/code_checkFbo.txt',
					print_r([
						'date' => date('G:i:s'),
						'page' => $page,
						'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
						'itemsCount' => count( $res['result']['rows'] ?? [] )
					], true),
					FILE_APPEND
				);

				if ( count($res['result']['rows']) < $data['limit'] ) $flag = false;

				if ( isset($res['result']['rows']) ) {
					$stockReport = array_merge( $stockReport, $res['result']['rows'] );
					foreach ($res['result']['rows'] as $key => $value) {
						if ( $value['free_to_sell_amount'] == 0 ) continue;
						$this->saveWarehouse( $value['warehouse_name'], $value['item_code'] );
						if ( empty($this->stockFbo[$value['item_code']]) ) {
						 $this->stockFbo[$value['item_code']] = intval($value['free_to_sell_amount']);
					 }else{
						 $this->stockFbo[$value['item_code']] += intval($value['free_to_sell_amount']);
					 }
					}
					file_put_contents(
						'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/code_checkFbo.txt',
						print_r([
							'date' => date('G:i:s'),
							'stockFboCount' => count( $this->stockFbo ?? [] ),
							'res' => $res
						], true),
						FILE_APPEND
					);

					file_put_contents(
						'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/checkFboCheck.log',
						print_r( array_keys($this->stockFbo), true )
					);
				} else {
					$timeEnd = date('Y.m.d G:i:s');
					var_dump($res);
					$try++;
					sleep(10);
				}

				$data['offset'] += 1000;
				sleep(8);
				$page++;
			}
			file_put_contents( "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/checkFbo.json", json_encode($stockReport) );
			var_dump( count( array_keys($this->stockFbo) ) );

		}

		private function saveWarehouse( string $whName, string $itemCode ):void
		{
			if (strpos(strtolower($whName), 'екатерин') === false
				|| strpos(strtolower($whName), 'красноя') === false
				|| strpos(strtolower($whName), 'новосиб') === false
				|| strpos(strtolower($whName), 'омск') === false
				|| strpos(strtolower($whName), 'калининг') === false
				|| strpos(strtolower($whName), 'хабаров') === false
				|| strpos(strtolower($whName), 'минск') === false
				|| strpos(strtolower($whName), 'астан') === false
				|| strpos(strtolower($whName), 'алмат') === false ) {
				$this->FBO_WH[ $itemCode ][] = $value;
			}
		}

		public function getIndMarkups(){
			if ($this->cabinet == 'TI') {
				$strSql = "SELECT * FROM individual_markups WHERE source = 'ozti'";
			} else if ($this->cabinet == 'IP') {
				$strSql = "SELECT * FROM individual_markups WHERE source = 'os'";
			}

			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			$this->markups = [];
			while ( $row = $results->Fetch() ){
				$this->markups[ $row['model'] ] = floatval($row['markup']);
			}
		}

		private function getSebes():void
		{
			if ( empty($this->stockFbo) ) throw new Exception("FBO report was not collected or data incorrect");

			$models = array_map(
				fn($item) => end( explode('_', $item) ),
				array_keys($this->stockFbo)
			);
			$models = array_filter( $models );

			if ( empty($models) ) throw new Exception("Cannot extract models list from FBO report");

			$service = PanelManager::getPriceManager();
			$servicePrice = $service->updatePriceService( "OS", "debug" );
			$servicePrice->market->setPriceFilter( [ 'article' => $models ] );
			$servicePrice->market->setConfig('tbl_sebes_fbo', false);
			$result = $servicePrice->getMinPurchasePrice();

			$prices = array_map( fn($item) => reset($item)['price'], $result );

			foreach ( $this->items as $key => &$v ){
				$v['SEBES'] = $prices[ $v['ARTICLE'] ] ?? '';
			}
		}

		public function getSebesLeg(){
			foreach ($this->items as $key => &$v) {
				$tmpPrice = array();
				unset($minPrice);
				unset($price_id);

				if ($this->cabinet == 'TI') {
					$strSql = "SELECT price, id, count, model, supplier_id FROM ci_price WHERE model = '".$v['ARTICLE']."' AND active_ozti = 'Y' ORDER BY price ASC" ;
				} else if ($this->cabinet == 'IP') {
					$strSql = "SELECT price, id, count, model, supplier_id FROM ci_price WHERE model = '".$v['ARTICLE']."' AND active_os = 'Y' ORDER BY price ASC" ;
				}

				$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
				while ($row = $results->Fetch()){
					$tmpPrice[$row['id']] = [
						'price' =>floatval($row['price']),
						'model' =>$row['model'],
						'count' => intval($row['count']),
						'supplier_id' => $row['supplier_id']
					];
					// if (!in_array($row['supplier_id'],array("47","129","128","144","141"))) {
					// }
				}
				//print_r($tmpPrice);
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
				// print_r('#####');
				// print_r($tmpPrice);
				if (!empty($tmpPrice) && (!empty($minPrice) && !empty($price_id) )) {
					$v['SEBES'] = $minPrice;
					$v['PRICE_ID'] = $price_id;
				} else {
					$v['SEBES'] ='';
					$v['PRICE_ID'] = '';
				}
		}
	 	//print_r($this->items);
	}

	public function getCiBrandIDLeg(){
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
	}

	private function getCiBrandID():void
	{
		$strSql = "SELECT id, bitrix_id FROM ci_brands";
		$res = $this->db->Query( $strSql );
		$brandsDict = [];
		while ( $row = $res->Fetch() ){
			$brandsDict[ $row['bitrix_id'] ] = $row['id'] ?? 0;
		}
		foreach ( $this->items as $key => &$item ){
			$item['BRAND_ID'] = $brandsDict[ $item['BRAND_BX'] ] ?? 0;
		}
	}

	public function getNewPrice($model, $brand_id,$newprice)
	{
		$price = new CPanelPricelist;
		$analysis = new CPanelAnalysis;
		$objCurrency = new CPanelCurrency;

		if ($this->cabinet == 'TI') {
			$price_id = 'ozti';
		} else if ($this->cabinet == 'IP') {
			$price_id = 'os';
		}

		$arSettings = array(
			"rate" => 1,
		);

		// print_r($brand_id);
		// print_r('<br>######<br>');
		// print_r($newprice);
		$arDefaultRRC = json_decode(CProSet::getOption("SETTINGS_RRC"), true)[$price_id];

		$arCurrency = $objCurrency->getDetail($arSettings["currency"]);

		$itemPrice = floatval($newprice);
		$productID = 0;
		$markup = 1;
		//print_r('<br>######<br>');
		//$itemPrice = (float)round($itemPrice, $arSettings["round"]);
		$profile = $analysis->getListByFilter(array("brand_id" => $brand_id,'price_id'=>$price_id));
		//print_r($profile);
		// print_r('<br>');
		// print_r($profile);
		if( is_array($profile) && !empty($profile) ){
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
		if ( $model != false ){
			if ( !empty($this->markups[$model]) ){
				$itemPrice = $itemPrice * $this->markups[$model];
				$priceArr = array('price' => $itemPrice, 'm' => $this->markups[$model]);
				return $priceArr;
			}
		}

		$itemPrice = round($itemPrice * $markup, 0);
		$priceArr = array('price' => $itemPrice, 'm' => $markup);
		return $priceArr;
	}

	public function GetTurnover()	{
		$this->fromMS = array();
		$ms = new MoyskladAPI('s1');
		$msItems = $ms->getTurnoverDay(2);
		//print_r($msItems);
		foreach ($msItems as $key => $value) {
			if (!empty($value['assortment']['article']) and ($value['income']['quantity'] != '0') and empty($this->fromMS[$value['assortment']['article']]) ) {
				$this->fromMS[$value['assortment']['article']] = intval(($value['income']['sum'] / 100) / $value['income']['quantity']);
			}
		}
		for ( $i = 1; $i <= 3; $i++ ){
			$msItems = $ms->getTurnoverWeek($i);

			foreach ($msItems as $key => $value) {
				// if ($value['article'] == 'GM-2100-1A') {
				// 	print_r($i);
				// 	print_r('###');
				// 	print_r($value);
				// 	die();
				// }
				if (!empty($value['assortment']['article']) and ($value['income']['quantity'] != '0') and empty($this->fromMS[$value['assortment']['article']]) ) {
					$this->fromMS[$value['assortment']['article']] = intval(($value['income']['sum'] / 100) / $value['income']['quantity']);
				}
			}
		}
	}

	public function calculatePercentageDifference($number1, $number2) {
	    $average = (floatval($number1) + floatval($number2)) / 2;
	    $difference = floatval($number1) - floatval($number2);
	    $percentDifference = ($difference / $average) * 100;

	    return $percentDifference;
	}

	public function calculatePercentageDifferenceAlt($number1, $number2) {
			return 100 - ($number1 / ($number2 / 100)) ;
	}

  public function checkFBOStock()
  {
		if (!empty($this->stockFbo)) {
			$this->CurDB->Query("DELETE FROM ozon_fbo_stock_prev_{$this->cabinet} WHERE 1=1", false, $err_mess.__LINE__);

			foreach ($this->checkFBOStock as $key => $value) {

				$in = array(
					"article" => "'".$key."'",
				);

				$fields = implode(",", array_keys($in));
		    $values = implode(",",$in);

		    $sql = "INSERT INTO ozon_fbo_stock_prev_{$this->cabinet} ($fields) VALUES ($values)";
		    $this->CurDB->query($sql);

			}

		  $this->CurDB->Query("DELETE FROM ozon_fbo_price_{$this->cabinet} WHERE 1=1", false, $err_mess.__LINE__);
		  $this->CurDB->Query("DELETE FROM ozon_fbo_stock_{$this->cabinet} WHERE 1=1", false, $err_mess.__LINE__);
			$this->CurDB->Query("DELETE FROM ozon_fbo_sebes_{$this->cabinet} WHERE 1=1", false, $err_mess.__LINE__);
			//print_r($this->stockFbo);
			$com = $this->mcom / 100;
			$allCom = $com;
			$newCom = $this->mnewCom / 100;
			$printCom = $com * 100;
			$skdCom = ($com - $newCom) * 100;
			foreach ($this->stockFbo as $key => $value) {
				if (isset($this->items[$key])) {

					if ($value != 0 && $value >= 1) {

						$this->answer[$this->items[$key]['ARTICLE']]['asnw'] = '';

						if (isset($this->FBO_WH[$this->items[$key]['OZON_ARTICLE']])) {
							$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= 'Товар числится на СКЛАДАХ: ';
							foreach ($this->FBO_WH[$this->items[$key]['OZON_ARTICLE']] as $kkk => $vvv) {
								$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= $vvv['warehouse_name'].' ';

							}
							$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= '<br><br>';
							$in = array(
								"article" => "'".$this->items[$key]['ARTICLE']."'",
							);

							$fields = implode(",", array_keys($in));
							$values = implode(",",$in);

							$sql = "INSERT INTO ozon_fbo_stock_{$this->cabinet} ($fields) VALUES ($values)";
							$this->CurDB->query($sql);
							$this->control[] = [ $this->items[$key]['ARTICLE'] ];
						}


						if ( isset($this->fromMS[$this->items[$key]['ARTICLE']]) ) {

							//$flag = (intval($this->fromMS[$this->items[$key]['ARTICLE']]) <=  intval($this->items[$key]['SEBES']) ) ? intval($this->fromMS[$this->items[$key]['ARTICLE']]) : intval($this->items[$key]['SEBES']);
							if (!empty($this->items[$key]['SEBES'])) {
								$diff = self::calculatePercentageDifferenceAlt($this->items[$key]['SEBES'],$this->fromMS[$this->items[$key]['ARTICLE']]);
							} else {
								$diff = null;
							}

							if ( $diff == null ){
								$cost = $this->fromMS[$this->items[$key]['ARTICLE']];
								$whatCost = "<span style=\"color:red\">Отсутствует себес БТ или себес БТ равен МС. Устанавливаем от себеса МС</span><br>";
							}
							elseif( $diff >= $this->threshold ){
								$cost = $this->fromMS[$this->items[$key]['ARTICLE']];
								$whatCost = "<span style=\"color:green\">Цена в БТ меньше на ".round(abs($diff),3)."%. Устанавливаем от себеса МС</span><br>";
							}
							else{
								if ( !empty($this->fromMS[$this->items[$key]['ARTICLE']]) && !empty($this->items[$key]['SEBES']) ){
									$cost = min( $this->fromMS[$this->items[$key]['ARTICLE']], $this->items[$key]['SEBES'] );
									$whatCost = "<span style=\"color:red\">Отличаются на ".round(abs($diff), 3)."%. Устанавливаем от наименьшего</span><br>";
								}
								elseif( !empty($this->fromMS[$this->items[$key]['ARTICLE']]) && empty($this->items[$key]['SEBES']) ){
									$cost = $this->fromMS[$this->items[$key]['ARTICLE']];
									$whatCost = "<span style=\"color:red\">Отсутствует себес БТ. Устанавливаем от МС</span><br>";
								}
							}

							if ( $this->shouldZeroStocks ){
								if ( empty( $this->fromMS[ $this->items[$key]['ARTICLE'] ] ) || empty( $this->items[$key]['SEBES'] ) ) {
									$stock_diff == null;
								}else{
									$stock_diff = self::calculatePercentageDifferenceAlt(
										$this->fromMS[ $this->items[$key]['ARTICLE'] ],
										$this->items[$key]['SEBES']
									);
								}

								if ( !empty($stock_diff) && $stock_diff >= $this->stock_threshold && empty( $this->stockNFCost[$this->items[$key]['ARTICLE']] ) ){
									$humanval = round( $stock_diff, 2 );
									$whatCost = "<span style=\"color:green\">Себестоимость БТ больше на {$humanval}%. <br>Передаем остаток 0</span><br>";
									$excludeStockImport[] = $this->items[$key]['ARTICLE'];
								}
							}

							// if (intval($this->fromMS[$this->items[$key]['ARTICLE']]) == 0) {
							//     $flag = intval($this->items[$key]['SEBES']);
							// 		$whatCost = 'Себес из БТ ниже. Берем его';
							// } elseif (intval($this->items[$key]['SEBES']) == 0) {
							//     $flag = intval($this->fromMS[$this->items[$key]['ARTICLE']]);
							// 		$whatCost = 'Себес из МС ниже либо равен себесу из БТ';
							// } else {
							//     $flag = (intval($this->fromMS[$this->items[$key]['ARTICLE']]) <= intval($this->items[$key]['SEBES'])) ?
							//         intval($this->fromMS[$this->items[$key]['ARTICLE']]) : intval($this->items[$key]['SEBES']);
							// 		$checkF = intval($this->fromMS[$this->items[$key]['ARTICLE']]) <=  intval($this->items[$key]['SEBES']);
							// 		$whatCost = $checkF ? 'Себес из МС ниже либо равен себесу из БТ' : 'Себес из БТ ниже. Берем его';
							// }

							// $newprice = self::getNewPrice($this->items[$key]['ARTICLE'],$this->items[$key]['BRAND_ID'],$this->fromMS[$this->items[$key]['ARTICLE']]);

							$priceArray = self::getNewPrice( $this->items[$key]['ARTICLE'],$this->items[$key]['BRAND_ID'], $cost );

							if ( !empty( $this->stockNFCost[$this->items[$key]['ARTICLE']] ) ){
								$isSubst = '';
								$cost = $this->stockNFCost[ $this->items[$key]['ARTICLE'] ];

								$whatCost = "<span style=\"color:red\">Товар есть на складе WR. Себестоимость {$cost} установлена принудительно</span><br>";
								$priceArray = self::getNewPrice(
									$this->items[$key]['ARTICLE'],
									$this->items[$key]['BRAND_ID'],
									$cost
								);

								// var_dump( $cost );
								// var_dump( $priceArray );
								// var_dump( $this->items[$key]['ARTICLE'] );
							}

							$newprice = $priceArray['price'];
							$markap = $priceArray['m'];
							$oldprice = $newprice;
							$newprice = round(($newprice * (1 - $allCom)) / (1 - $newCom));
							$this->answer[$this->items[$key]['ARTICLE']]['count'] = $value;
							$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= "Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
							Остаток на FBO OZON: {$value}<br>
							Себес из МС:" . $this->fromMS[$this->items[$key]['ARTICLE']] . "<br>
							Себес из БТ:" . $this->items[$key]['SEBES'] . "<br>
							{$whatCost}
							<span style=\"color:green\">Устанавливаем новую цену</span>:{$newprice}<br><br>
							<span>Наценка из настроек РРЦ</span>: {$markap}<br><br>
							Комиссия OZON: {$printCom}% , скидка на комиcсию для ФБО {$skdCom}%<br>ЦЕНА ФБО:<br>{$newprice} ";
							$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= "= round(($oldprice * (1 - $allCom)) / (1 - $newCom))<hr><br><br>";

							$in = array(
								"model" => "'".$this->items[$key]['ARTICLE']."'",
								"brand_id" => "'".($this->items[$key]['BRAND_ID'] ?? 0)."'",
								"sebes" => "'".$cost."'",
							);

							$fields = implode(",", array_keys($in));
							$values = implode(",",$in);

							$sql = "INSERT INTO ozon_fbo_sebes_{$this->cabinet} ($fields) VALUES ($values)";
							$this->CurDB->query($sql);

							$in = array(
								"article" => "'".$this->items[$key]['ARTICLE']."'",
								"price" => "'".$newprice."'",
							);
							$fields = implode(",", array_keys($in));
							$values = implode(",",$in);

							$sql = "INSERT INTO ozon_fbo_price_{$this->cabinet} ($fields) VALUES ($values)";
							$res = $this->CurDB->query($sql);
						} else {
							$priceArray = self::getNewPrice( $this->items[$key]['ARTICLE'],$this->items[$key]['BRAND_ID'], $this->items[$key]['SEBES']);
							$newprice = $priceArray['price'];
							$markap = $priceArray['m'];
							$whatCost = '';
							// if ( !empty( $this->stockNFCost[$this->items[$key]['ARTICLE']] ) ){
							// 	$isSubst = '';
							// 	$cost = $this->stockNFCost[ $this->items[$key]['ARTICLE'] ];
							// 	$whatCost = "<span style=\"color:red\">Товар есть на складе NF. Себестоимость {$cost} установлена принудительно</span><br>";
							// 	$priceArray = self::getNewPrice(
					    //     $this->items[$key]['ARTICLE'],
					    //     $this->items[$key]['BRAND_ID'],
					    //     $cost
					    //   );
							// 	var_dump( $cost );
							// 	var_dump( $priceArray );
							// 	var_dump( $this->items[$key]['ARTICLE'] );
							// }

							$oldprice = $newprice;
							$newprice = round(($newprice * (1 - $allCom)) / (1 - $newCom));

							$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= "Товар <b>{$this->items[$key]['ARTICLE']}</b><br>
							Остаток на FBO OZON: {$value}<br>
							<span style=\"color:red\">Отсутствует в отчете МС</span><br>
							Себес из БТ:{$this->items[$key]['SEBES']}<br><span style=\"color:green\">Оставляем цену РРЦ</span>:{$newprice}<br><br>
							<span>Наценка из настроек РРЦ</span>: {$markap}<br><br>
							Коммисия OZON: {$printCom}% , скидка на коммисию для ФБО {$skdCom}%<br>ЦЕНА ФБО:<br>{$newprice} ";
							$this->answer[$this->items[$key]['ARTICLE']]['asnw'] .= "= round(($oldprice * (1 - $allCom)) / (1 - $newCom))<hr><br><br>";


							if ( $newprice > 0 ){
								$in = array(
									"article" => "'".$this->items[$key]['ARTICLE']."'",
									"price" => "'".$newprice."'",
								);
								$fields = implode(",", array_keys($in));
								$values = implode(",",$in);

								$sql = "INSERT INTO ozon_fbo_price_{$this->cabinet} ($fields) VALUES ($values)";
								$res = $this->CurDB->query($sql);

								$in = array(
									"model" => "'".$this->items[$key]['ARTICLE']."'",
									"brand_id" => "'".($this->items[$key]['BRAND_ID'] ?? 0)."'",
									"sebes" => "'".$this->items[$key]['SEBES']."'",
								);

								$fields = implode(",", array_keys($in));
								$values = implode(",",$in);

								$sql = "INSERT INTO ozon_fbo_sebes_{$this->cabinet} ($fields) VALUES ($values)";
								$this->CurDB->query($sql);
							}

						}

					}
					//}
				}
				$this->sppAnalyticsData[ $this->items[$key]['ARTICLE'] ] = $value;
				file_put_contents(
					$this->excludeStockImportPath,
					json_encode( $excludeStockImport )
				);
			}

		}
  }

	public function PrintResult()
	{
		file_put_contents("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/ozon/logs/{$this->cabinet}/fbo/log.txt", print_r(json_encode($this->answer), true).PHP_EOL);
		file_put_contents("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/ozon/export/analytics.json", print_r(json_encode($this->sppAnalyticsData), true));
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

	private function checkStockDifference():void
	{
		$cab = $this->cabinet;
		$module = "ozon_checkFbo_{$cab}";
		$nameTemplateSys = "checkFbo_{$cab}";
		$nameTemplateSys2 = "checkFbo_diff_{$cab}";
		$nameTemplateBot = "ФБО_OZON_{$cab}";
		$nameTemplateBot2 = "ФБО_Разница_OZON_{$cab}";
		$messageHeader = "<b>Модуль ФБО OZON {$cab}:</b>\n\n";

		$filenamePrev = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/ozon/export/{$nameTemplateSys}_prev.xlsx";
		$filenameLast = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/ozon/export/{$nameTemplateSys}_last.xlsx";

		$row = $this->CurDB->select(['*'], 'modules_control')->where('module', $module)->make();
		$oldValue = intval( $row[0]['old_value'] );
		$newValue = intval( $row[0]['new_value'] );
		$lastUploadTime = $row[0]['date'];
		$control = count($this->control);
		$dataForBot = $this->control;

		if ( $newValue != 0 ){
			$diff = ($newValue - $control) / $newValue * 100;
		}else{
			$diff = 0;
		}

		$diffInfo = abs( round($diff, 2) );
		print_r("difference - {$diff}\n");
		// $this->bot_threshold
		if ( $diffInfo >= $this->bot_threshold ){

			if ($diff > 0 ){
				$cWord = 'меньше';
			}else{
				$cWord = 'больше';
			}

			$this->bot->sendMessage("{$messageHeader}<b>⚠На ФБО числится на <u>{$diffInfo}%</u> {$cWord} товаров!</b>\n\nВремя предыдущего запуска: {$lastUploadTime}\n\n✅ <b>Прошлая итерация: <u>{$newValue}</u></b>.\n❌ <b>Текущая итерация: <u>{$control}</u></b>\n\n<i>Убедитесь в отсутствии ошибок!</i>\n<i>Ниже прикреплены файлы с артикулами выгрузок</i>");
			if ( file_exists($filenamePrev) ){
				$this->bot->sendFile($filenamePrev, "{$nameTemplateBot}_prev.xlsx");
			}

		}

		if ( file_exists($filenameLast) )	rename($filenameLast, $filenamePrev);

		$newFile = $this->buildXlsx( 'ozon', "{$nameTemplateSys}_last", ['Модель'], $dataForBot );
		// $arDiff = $this->compareUpload( $filenamePrev, $dataForBot );
		if ( $diffInfo >= $this->bot_threshold ){

			if ($diff > 0 ){
				$arDiff = $this->compareUploadLess( $filenamePrev, $dataForBot );
				$diffFile = $this->buildXlsx( 'ozon', "{$nameTemplateSys2}", ['Модель'], $arDiff );
			}else{
				$arDiff = $this->compareUploadMore( $filenamePrev, $dataForBot );
				$diffFile = $this->buildXlsx( 'ozon', "{$nameTemplateSys2}", ['Модель'], $arDiff );
			}

			if ( $newFile ){
				$this->bot->sendFile($newFile, "{$nameTemplateBot}_last.xlsx");
				if ( $diffFile ) $this->bot->sendFile($diffFile, "{$nameTemplateBot2}.xlsx");
			}else{
				$this->bot->sendMessage("Файл {$nameTemplateBot}_last.xlsx не может быть отправлен ввиду непредвиденной ошибки: массив пустой");
			}

		}

		if ( $newValue == 0 && $control == 0 ){
			$this->bot->sendMessage("{$messageHeader}<b>❌Числится нулевое количество товаров в двух или более итерациях подряд.</b>\n\n⚠<i>Необходимо срочно исправить ошибку!</i>");
		}
		$date = date('Y-m-d G:i:s');
		$strSql = " UPDATE modules_control SET old_value = '{$newValue}', new_value = '{$control}', date = '{$date}' WHERE module = '{$module}'";
		$this->CurDB->query( $strSql );
	}

	private function buildXlsx( string $module, string $name, array $headers, array $data ):string|bool
	{
		if (!class_exists('SpreadsheetReader')){
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}

		if ( empty($data) ) return false;

		$xls = new PHPExcel();
    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $sheet->setTitle('List 1');

		$alphabet = range('A', 'Z');
		foreach ( $headers as $key => $value ){
			$sheet->setCellValueExplicit("{$alphabet[$key]}1", $value, PHPExcel_Cell_DataType::TYPE_STRING);
		}
		foreach ( $data as $i => $value ){
			$row = $i + 2;
			foreach ( $value as $k => $elem ){
				$sheet->setCellValueExplicit("{$alphabet[$k]}{$row}", $elem, PHPExcel_Cell_DataType::TYPE_STRING);
			}
		}
		$objWriter = new PHPExcel_Writer_Excel2007($xls);
    $filename = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/{$module}/export/{$name}.xlsx";
    $objWriter->save( $filename );

		return $filename;
	}

	private function compareUploadLess(string $path, array $data):array
	{
		if (!class_exists('SpreadsheetReader')){
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}

		$xls = PHPExcel_IOFactory::load($path);
		$xls->setActiveSheetIndex(0);
		$sheet = $xls->getActiveSheet();

		$arModelsXlsx = [];

		$arUpload = [];
		foreach( $data as $row ){
			$arUpload[ $row[0] ] = $row[0];
		}

		$arDiff = [];
		foreach ( $sheet->toArray() as $i => $row ) {
			if ( $i == 0 ) continue;
			if ( empty($arUpload[$row[0]]) ){
					$arDiff[] = [ $row[0] ];
			}
		}
		return $arDiff;
	}

	private function compareUploadMore(string $path, array $data):array
	{
		if (!class_exists('SpreadsheetReader')){
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}

		$xls = PHPExcel_IOFactory::load($path);
		$xls->setActiveSheetIndex(0);
		$sheet = $xls->getActiveSheet();

		$arModelsXlsx = [];

		foreach ( $sheet->toArray() as $i => $row ) {
			if ( $i == 0 ) continue;
			$arModelsXlsx[ $row[0] ] = [ $row[0] ];
		}

		$arDiff = [];
		$arUpload = [];
		foreach( $data as $row ){
			if ( empty($arModelsXlsx[$row[0]]) ){
					$arDiff[] = [ $row[0] ];
			}
		}
		return $arDiff;
	}

	private function getStockImportItems():array
	{
		$strSql = "SELECT model FROM ci_price WHERE supplier_id IN (141, 144)";
		$res = $this->db->Query( $strSql );

		if ( $res->SelectedRowsCount() <= 0 ) return [];

		$models = [];
		while ( $row = $res->Fetch() ){
			$models[] = $row['model'];
		}

		return $models;
	}


}

// (new checkFBONEW('TI'))->run();
