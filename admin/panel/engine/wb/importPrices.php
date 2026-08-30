<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workerArgs = array();
if (!empty($_SERVER["argv"])) {
	foreach (array_slice($_SERVER["argv"], 1) as $arg) {
		if ($arg === "" || $arg === "-f" || (isset($arg[0]) && $arg[0] === "-")) {
			continue;
		}
		$workerArgs[] = $arg;
	}
}
$workerKey = implode(" ", $workerArgs);
$workerMap = array(
	"CABINET=WR" => "engine_wb_importPrices_php_CABINET_WR",
	"CABINET=TL" => "engine_wb_importPrices_php_CABINET_TL",
	"CABINET=WT" => "engine_wb_importPrices_php_CABINET_WT",
);
$workerId = isset($workerMap[$workerKey]) ? $workerMap[$workerKey] : "engine_wb_importPrices_php_CABINET_WR";
$workers = new WorkersChecker($workerId);
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

	class WbImportPrices{
		public function __construct(){
			global $DB;
			$this->loadModules();
			$this->db = $DB;

			$strSql = "SELECT * FROM wdhs_wb_main_settings";

			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
			  $this->arSetting[$row['cabinet']] = $row;
			}

			$this->arLog = array();
			$this->date = date('d-m-Y');

		}


		private function loadModules(){
			Loader::includeModule("main");
			Loader::includeModule("iblock");
			Loader::includeModule("panel.manager");
	    }

		public function run(){

			foreach ((array)$_SERVER['argv'] as $v){
				list($k,$v) = explode("=",$v);
				if ($k && $v) $_REQUEST[$k] = $v;
			}

			// Считаем на сколько домножать в зависимости от скидки
			if ( $_REQUEST['CABINET'] == 'WR' || $_REQUEST['CABINET'] == 'WT' ){
				$m = CProSet::getOption('CATALOG_SALE_wb');
				$discPercent = intval( empty($m) ? 20 : $m );
			}else{
				$m = CProSet::getOption('CATALOG_SALE_wbtl');
				$discPercent = intval( empty($m) ? 20 : $m );
			}

			$this->discount = $discPercent;
			$this->multiplier = 1 / (1 - $discPercent / 100);

			$this->arLog['TIME_START'] = date('H:i:s');
			$this->arLogSend['TIME_START'] = date('H:i:s');
			$this->CurDB = new DBPanel;
			if(isset($this->arSetting[$_REQUEST["CABINET"]])){
				$this->clientId = $this->arSetting[$_REQUEST["CABINET"]]['clientId'];
				$this->token = $this->arSetting[$_REQUEST["CABINET"]]['api'];
				$this->settings = json_decode($this->arSetting[$_REQUEST["CABINET"]]['settings'],true);

				$this->module = 'ImportPrice_'. $_REQUEST['CABINET'];
				if (!$this->settings['upload_price']) die('upload_price = 0');

				$checkConnection = $this->checkConnection();
				if (!$checkConnection) die('connection false');
				$arStat = [
					'status' => 'IN_PROCESS',
					'status_text' => 'Получение товаров',
					'percent' => '0',
					'time_start' => date('Y.m.d G:i:s')
				];
				$this->updateStatus($this->module, $arStat);
				$this->wbTop = $this->getWBTop();

				if (!is_array($this->wbTop)) {
					$this->arLog['CRITICAL_ERROR'][] = 'ТОП WB пуст!';
					die('ТОП WB пуст!');
				}

				$this->arLog['WB_TOP']['COUNT'] = count($this->wbTop);
				$this->arLog['WB_TOP']['ITEMS'] = json_encode($this->wbTop);
				//Проверки
				$this->updateStatus($this->module, ['status_text' => 'Выполнение проверок', 'percent' => '10']);
				$this->checkQuarantine();
				$this->checkFBO($_REQUEST["CABINET"]);
				$this->getNMID($_REQUEST["CABINET"]);

				//Получение из БТ
				$this->updateStatus($this->module, ['status_text' => 'Получение товаров', 'percent' => '20']);
				$this->getItems($_REQUEST["CABINET"]);

				//подготовка к загрузке
				if (count($this->items) == 0) {
					$this->updateStatus($this->module, ['status_text' => 'Пустой массив при выборке из Битрикса', 'percent' => '100', 'status' => 'ERROR']);
					$this->arLog['CRITICAL_ERROR'][] = 'Пустой массив при выборке из Битрикса';
					die('Пустой массив при выборке из Битрикса');
				}
				$this->updateStatus($this->module, ['status_text' => 'Сборка массива', 'percent' => '35']);
				$this->buildArray();

				if (count($this->dataArray) == 0) {
					$this->updateStatus($this->module, ['status_text' => 'Пустой массив для отправки на ВБ', 'percent' => '100', 'status' => 'ERROR']);
					$this->arLog['CRITICAL_ERROR'][] = 'Пустой массив для отправки на ВБ';
					die('Пустой массив для отправки на ВБ');
				}
				$this->updateStatus($this->module, ['status_text' => 'Отправка цен', 'percent' => '60']);
				$this->sendPrice();
			} else {
				$this->arLog['CRITICAL_ERROR'][] = 'Не действительный кабинет!';
				die('Не действительный кабинет!');
			}
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/reqests/prices/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r(json_encode($this->arLogSend), true).PHP_EOL,FILE_APPEND);
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/reqests/prices/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r('#####', true).PHP_EOL,FILE_APPEND);
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/prices/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/prices/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r('#####', true).PHP_EOL,FILE_APPEND);
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/test.txt", print_r($el, true).PHP_EOL,FILE_APPEND);

			$this->db->Update("wdhs_wb_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='price' AND cabinet = '".$_REQUEST["CABINET"]."'", $err_mess.__LINE__);
			$arStat = [
				'status' => 'COMPLETED',
				'status_text' => 'Выполнено',
				'percent' => '100',
				'time_end' => date('Y.m.d G:i:s')
			];
			$this->updateStatus($this->module, $arStat);
		}

		private function getPriceData():array
		{
			return [];

			$strSql = "SELECT * FROM ci_price WHERE active_wb = 'Y'";
			$rows = $this->db->Query( $strSql );
			$result = [];

			while ( $row = $rows->Fetch() ){
				$result[ $row['model'] ] = 1;
			}

			return $result;
		}

		public function getDynamicPrice( $cabinet ):array
		{
			if ( $cabinet != 'WR' ) return [];
			$rows = $this->CurDB->select(['model', 'price'], "wb_dp_prices")->make();
			$result = [];

			foreach ( $rows as $row ){
				$result[ $row['model'] ] = $row['price'];
			}

			return $result;
		}

		public function getItems($cab){
			switch ( $cab )
			{
				case 'WR':
					$priceIndex = 'WBPRICE';
					$rate = 1; // Конвертация валют
					$multiplierA = 1; // Доп множитель. Нужен только для WT
					$multiplierB = 1;
					break;
				case 'WT':
					$priceIndex = 'MINIMUM_PRICE_RB';
					$rate = $this->getRate('BYN');
					$multiplierA = 1.6;
					$multiplierB = $this->multiplier;
					break;
				case 'TL':
					$priceIndex = 'WBTL_PRICE';
					$rate = 1;
					$multiplierA = 1;
					$multiplierB = 1;
					break;
				default:
					die("CABINET MISMATCH\n");
			}

	    $arSelect = Array("ID","IBLOCK_ID","PROPERTY_AEN2","PROPERTY_CML2_ARTICLE","PROPERTY_".$priceIndex."");

	    $arFilter = Array(
	      "IBLOCK_ID" => CProSet::IB_CATALOG,
				"=PROPERTY_CML2_ARTICLE" => $this->wbTop
				// "PROPERTY_CML2_ARTICLE" => array('A-158WA-1')
	    );

			// if ( $cab == 'WT' ){
			// 	$arFilter["PROPERTY_CML2_ARTICLE"] = array('A-158WA-1');
			// }
			$dynamicPrices = $this->getDynamicPrice( $cab );

	    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
			$this->arLog['NOT_NMID']['COUNT'] = 0;
			$this->arLog['NOT_PRICE']['COUNT'] = 0;
			$this->arLog['FBO']['COUNT'] = 0;
			$this->arLog['FBO']['ITEMS'] = [];
			// $importStockModels = $this->getStockImport();
			$priceData = $this->getPriceData();
			$this->items = array();
	    while ($el = $result->GetNext()){

			  if (empty($el["PROPERTY_".$priceIndex."_VALUE"]) || $el["PROPERTY_".$priceIndex."_VALUE"] == '') {
					$this->arLog['NOT_PRICE']['COUNT'] = $this->arLog['NOT_PRICE']['COUNT'] + 1;
					$this->arLog['NOT_PRICE']['ITEMS'][] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
				} else if (!isset($this->arNMID[$el["ID"]]) || $this->arNMID[$el["ID"]] == '') {
					$this->arLog['NOT_NMID']['COUNT'] = $this->arLog['NOT_PRICE']['COUNT'] + 1;
					$this->arLog['NOT_NMID']['ITEMS'][] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
				} else {
					if ($cab == 'WR') {
						if (isset($this->checkFBOPrice[trim($el["PROPERTY_CML2_ARTICLE_VALUE"])])) {
							// $price = intval($this->checkFBOPrice[$el["PROPERTY_CML2_ARTICLE_VALUE"]]) * $this->multiplier;
							$price = $el["PROPERTY_".$priceIndex."_VALUE"];
							$this->arLog['FBO']['ITEMS'][] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
							$this->arLog['FBO']['COUNT'] = $this->arLog['FBO']['COUNT'] + 1;
						}
						// elseif ( isset($dynamicPrices[$el['PROPERTY_CML2_ARTICLE_VALUE']]) ) {
						// 	$price = $dynamicPrices[$el['PROPERTY_CML2_ARTICLE_VALUE']] * $this->multiplier;
						// }
						else {
							$price = $el["PROPERTY_".$priceIndex."_VALUE"];
						}
					} else {
						$price = $el["PROPERTY_".$priceIndex."_VALUE"];
					}

					if ( $cab == 'WR' ){
						if ( isset($dynamicPrices[$el['PROPERTY_CML2_ARTICLE_VALUE']]) ) {
							$price = round($dynamicPrices[$el['PROPERTY_CML2_ARTICLE_VALUE']] * $this->multiplier);
						}
						if ( isset($this->checkFBOPrice[ $el["PROPERTY_CML2_ARTICLE_VALUE"] ]) ){
							$price = $el["PROPERTY_".$priceIndex."_VALUE"];
						}
					}

					// if ( in_array($el["PROPERTY_CML2_ARTICLE_VALUE"], $importStockModels) ) continue; // Если модель есть на складе импорт, цены не выгружаем на них

					$this->items[$el['ID']] = [
						'ID' => $el['ID'],
						'ARTICLE' => $el['PROPERTY_CML2_ARTICLE_VALUE'],
						'NMID' => $this->arNMID[$el["ID"]],
						'PRICE' => $price * $rate * $multiplierA *$multiplierB,
						'ONLY_DISCOUNT' => false,
					];

					if ( empty($priceData[$el['PROPERTY_CML2_ARTICLE_VALUE']]) ) {
						$this->items[$el['ID']]['ONLY_DISCOUNT'] = true;
					}
				}
	    }

			// var_dump($this->items[14124]);
			// var_dump( $dynamicPrices['A-159WA-N1'] );
			// var_dump($this->multiplier);
			// die;

			$this->arLog['NOT_PRICE']['ITEMS'] = json_encode($this->arLog['NOT_PRICE']['ITEMS']);
			$this->arLog['NOT_NMID']['ITEMS'] = json_encode($this->arLog['NOT_NMID']['ITEMS']);

		}

		public function buildArray(){
			$this->arLog['AMOUNT']['COUNT'] = 0;
			$tmp = [];
			foreach ($this->items as $key => $value) {
				$tmp = [
					"nmID" => intval($value['NMID']),
					"price" => intval($value['PRICE']),
					"discount" => intval( $this->discount )
				];
				// if ( $value['ONLY_DISCOUNT'] === true ){
				// 	unset( $tmp['price'] );
				// }


				$prices[] = $tmp;

				$this->arLog['AMOUNT']['COUNT'] = $this->arLog['AMOUNT']['COUNT'] + 1;
				$this->arLog['AMOUNT']['ITEMS'][] = ['article' => $value['ARTICLE'], 'price' => $value['PRICE']];
			 }
			 // die("GETET\n");
			 $this->arLog['AMOUNT']['ITEMS'] = json_encode($this->arLog['AMOUNT']['ITEMS']);

			 $this->dataArray = array_chunk($prices,1000);

			 file_put_contents(
				 "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/dataArray.txt",
				 print_r($this->dataArray, true),
				 FILE_APPEND
			 );

		}

		public function getWBTop() {
			$strSql = "SELECT * FROM ci_wb_top";
			$arIDs = array();
			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$tops[$row["bitrix_id"]] = $row["article"];
			}

			$filter = [
				'WR' => 'active_wb',
				'TL' => 'active_wbtl',
				'WT' => 'active_wb',
			];

			if ( in_array( $_REQUEST['CABINET'], ['WR', 'TL', 'WT'] ) ){
				$tops = [];
				$strSql = "SELECT * FROM ci_price WHERE {$filter[$_REQUEST['CABINET']]} = 'Y' AND supplier_id NOT IN (74)";
				$result = $this->db->Query( $strSql );
				while ( $row = $result->Fetch() ){
					$tops[ $row['bitrix_id'] ] = $row['model'];
				}

				$fbo = $this->getFullFboInfo();
				$tops = array_merge( $tops, $fbo );
			}
			return $tops;
		}

		private function getFullFboInfo():array
		{
			$cab = $_REQUEST['CABINET'];
			if ( $cab == 'WT' ) $cab = "WR";
			$dbPanel = new DBPanel;
			$rows = $dbPanel->select(['*'], "wb_fbo_price_{$cab}")->make();

			$fbo = [];

			foreach ( $rows as $row ){
				$fbo[] = $row['article'];
			}
			if ( empty($fbo) ) return [];
			$rows = CIBlockElement::getList(
				[],
				['IBLOCK_ID'=> 16, "=PROPERTY_CML2_ARTICLE" => $fbo],
				false,
				false,
				["IBLOCK_ID", "ID", "PROPERTY_CML2_ARTICLE"]
			);
			$result = [];

			while ( $row = $rows->getNext() ){
				$result[$row['ID']] = $row['PROPERTY_CML2_ARTICLE_VALUE'];
			}

			return $result;
		}

		private function getRate( string $currency ):float
		{
			$strSql = "SELECT rate FROM ci_currency WHERE id = '{$currency}'";

			try{
				$rate = $this->db->Query( $strSql )->Fetch()['rate'];
			} catch( Throwable $e ) {
				var_dump( $e->getMessage() );
				die( "Error with rate select\n" );
			}

			if ( empty($rate) ) die( "Rate cannot be empty or be equal to zero\n" );

			return $rate;
		}

		public function checkQuarantine() {
			$arIDs = array_keys($this->wbTop);
			$strSql = "SELECT * FROM ci_price_quarantine WHERE PRODUCT_ID IN ('".implode("','", $arIDs)."') AND (SITE_ID = 'wb' OR PRICE_ID = 'WB')";
			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			$this->arLog['QUARANTINE']['COUNT'] = 0;
			while ($row = $results->Fetch()){
				$this->arLog['QUARANTINE']['COUNT'] = $this->arLog['QUARANTINE']['COUNT'] + 1;
				$this->arLog['QUARANTINE']['ITEMS'][] = $this->wbTop[$row["PRODUCT_ID"]];
				unset($this->wbTop[$row["PRODUCT_ID"]]);
			}
			$this->arLog['QUARANTINE']['ITEMS'] = json_encode($this->arLog['QUARANTINE']['ITEMS']);
		}

		public function checkFBO( $cab ) {

			// $this->checkFBOPrice = [];
			// return;

			if ( $cab == 'WT' ) $cab = "WR";
			$dbPanel = new DBPanel;
			$rows = $dbPanel->select(['*'], "wb_fbo_price_{$cab}")->make();
			$this->checkFBOPrice = [];
			foreach ( $rows as $row ){
				$this->checkFBOPrice[ $row['article'] ] = $row['price'];
			}
			// if ($cab == 'WR') {
			// 	$strSql = "SELECT * FROM wdhs_wb_fbo_price";
			// 	$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			// 	while ($row = $results->Fetch()){
			// 		$this->checkFBOPrice[$row['article']] = $row['price'];
			// 	}
			// } else if ($cab == 'TL') {
			// 	$strSql = "SELECT * FROM wdhs_wb_fbo_price_TL";
			// 	$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			// 	while ($row = $results->Fetch()){
			// 		$this->checkFBOPrice[$row['article']] = $row['price'];
			// 	}
			// } else {
			// 	$this->checkFBOPrice = array();
			// }
		}

		public function getNMID($cab) {
			if ($cab == 'WR') {
					$strSql = "SELECT * FROM wdhs_wb_props WHERE `cabinet` = 'WR'";
			} else if ($cab == 'WT') {
					$strSql = "SELECT * FROM wdhs_wb_props WHERE `cabinet` = 'WT'";
			} else if ($cab == 'TL') {
					$strSql = "SELECT * FROM wdhs_wb_props WHERE `cabinet` = 'TL'";
			}
			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$this->arNMID[ $row['bitrix_id'] ] = $row['nmid'];
			}
		}

		public function sendPrice(){

			foreach ($this->dataArray as $key => $value) {
				$data = ['data' => $value];
				$res = $this->sendRequest('https://discounts-prices-api.wildberries.ru/api/v2/upload/task','POST', $data);
				$this->arLogSend[] = $res;
				sleep(3);
			}
			file_put_contents(
				'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/importPricesRES.log',
				print_r($this->arLogSend, 1)
			);
			file_put_contents(
				'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/importPricesBODY.log',
				print_r($this->dataArray, 1),
			);
		}

		public function checkConnection(){
			$check = $this->sendRequest('https://content-api.wildberries.ru/ping','GET');
			if (isset($check['Status']) && $check['Status'] == 'OK') {
				return true;
			} else if (isset($check['status']) && $check['status'] == '401') {
				$this->arLog['CRITICAL_ERROR'][] = 'Проблема с ключом API';
				return false;
			} else if (isset($check['status']) && $check['status'] == '405') {
				$this->arLog['CRITICAL_ERROR'][] = 'Слишком много запросов!';
				return false;
			} else {
				return false;
			}
		}

		public function sendRequest($request,$method = 'POST',$data=array()){
				$data_string = json_encode($data);
				$ch = curl_init($request);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Authorization: ' . $this->token,
				));
				if ($method == 'POST' || $method == 'PUT'){
					curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
				}
				if ($method == 'PUT'){
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
				}
				if ($method == 'POST'){
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
				}
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_HEADER, false);
				$res = curl_exec($ch);
				curl_close($ch);

				$res = json_decode($res, true);

				return $res;
		}

		public function updateStatus( string $code, array $arStat ):void
	  {
	    if ( empty($arStat) ) return;
	    $strSql = "UPDATE wb_agents SET ";
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

		public function getStockImport():array
		{
			$strSql = "SELECT model FROM ci_price WHERE supplier_id = 141";
			$res = $this->db->Query( $strSql );

			if ( $res->SelectedRowsCount() <= 0  ) return [];

			$models = [];
			while ( $row = $res->Fetch() ){
				$models[] = $row['model'];
			}

			return $models;
		}
}

(new WbImportPrices())->run();
