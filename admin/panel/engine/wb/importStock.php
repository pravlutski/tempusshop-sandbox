<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

set_time_limit(0);
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
	"CABINET=WR" => "engine_wb_importStock_php_CABINET_WR",
	"CABINET=TL" => "engine_wb_importStock_php_CABINET_TL",
	"CABINET=WT" => "engine_wb_importStock_php_CABINET_WT",
);
$workerId = isset($workerMap[$workerKey]) ? $workerMap[$workerKey] : "engine_wb_importStock_php_CABINET_WR";
$workers = new WorkersChecker($workerId);
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
require("classes/StocksDataProvider2.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class WbImportStocks{
	public function __construct(){
		global $DB;
		$this->loadModules();
		$this->db = $DB;
		$this->CurDB = new DBPanel;
		$strSql = "SELECT * FROM wdhs_wb_main_settings";

		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $this->arSetting[$row['cabinet']] = $row;
		}
		$this->arLog = array();
		$this->date = date('d-m-Y');

		$this->bot = new TGNotifier;
		$this->module = '';
		$this->barcodes = [];
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
		$this->arLog['TIME_START'] = date('H:i:s');
		$this->arLogSend['TIME_START'] = date('H:i:s');
		if(isset($this->arSetting[$_REQUEST["CABINET"]])){
			$this->clientId = $this->arSetting[$_REQUEST["CABINET"]]['clientId'];
			$this->token = $this->arSetting[$_REQUEST["CABINET"]]['api'];
			$this->settings = json_decode($this->arSetting[$_REQUEST["CABINET"]]['settings'],true);
			$this->module = 'importStock_'.$_REQUEST['CABINET'];
			if (!$this->settings['upload_stock']) die('upload_stock = 0');

			$checkConnection = $this->checkConnection();
			if (!$checkConnection) die('connection false');
			$arStat = [
				'status' => 'IN_PROCESS',
				'status_text' => 'Получение товаров',
				'percent' => 0,
				'time_start' => date('Y.m.d G:i:s')
			];
			$this->updateStatus($this->module, $arStat);
			$this->wh = $this->getWH();
			$this->wbTop = $this->getWBTop();
			$this->stayZero = [];
			if (!is_array($this->wbTop)) {
				$this->arLog['CRITICAL_ERROR'][] = 'ТОП WB пуст!';
				die('ТОП WB пуст!');
			}

			$this->arLog['WB_TOP']['COUNT'] = count($this->wbTop);
			$this->arLog['WB_TOP']['ITEMS'] = json_encode($this->wbTop);
			//Проверки
			$this->updateStatus($this->module, ['status_text' => 'Выполнение проверок', 'percent' => 10]);
			$this->checkReserv();
			$this->checkQuarantine();
			$this->getItemsDictionary( $_REQUEST["CABINET"] );
			$this->checkFboStock($_REQUEST["CABINET"]);

			$this->checkActive($_REQUEST["CABINET"]);
			//Получение из БТ
			$this->updateStatus($this->module, ['status_text' => 'Получение товаров', 'percent' => 35]);
			$this->getSebes($_REQUEST["CABINET"]);
			$this->getItems();
			$this->getAll();

			//подготовка к загрузке
			if (count($this->items) == 0) {
				$this->updateStatus($this->module, ['status_text' => 'Пустой массив при выборке из Битрикса', 'percent' => 100, 'status' => 'ERROR']);
				$this->arLog['CRITICAL_ERROR'][] = 'Пустой массив при выборке из Битрикса';
				die('Пустой массив при выборке из Битрикса');
			}
			$this->updateStatus($this->module, ['status_text' => 'Сборка массива', 'percent' => 50]);
			$this->buildArray();
			if (count($this->dataArray) == 0) {
				$this->updateStatus($this->module, ['status_text' => 'Пустой массив для отправки на ВБ', 'percent' => 100, 'status' => 'ERROR']);
				$this->arLog['CRITICAL_ERROR'][] = 'Пустой массив для отправки на ВБ';
				die('Пустой массив для отправки на ВБ');
			}
			$this->updateStatus($this->module, ['status_text' => 'Отправление остатков', 'percent' => 70]);
			$this->sendStock();
			$this->checkStockDifference();



		} else {
			$this->arLog['CRITICAL_ERROR'][] = 'Не действительный кабинет!';
			die('Не действительный кабинет!');
		}
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/reqests/stock/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r(json_encode($this->arLogSend), true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/reqests/stock/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r('#####', true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/stock/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/stock/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r('#####', true).PHP_EOL,FILE_APPEND);
			$this->db->Update("wdhs_wb_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='stock' AND cabinet = '".$_REQUEST["CABINET"]."'", $err_mess.__LINE__);
			$arStat = [
				'status' => 'COMPLETED',
				'status_text' => 'Выполнено',
				'percent' => 100,
				'time_end' => date('Y.m.d G:i:s')
			];
			$this->updateStatus($this->module, $arStat);
	}

	private function getItemsDictionary(string $cabinet):void
	{
		$strSql = "SELECT * FROM wdhs_wb_props WHERE cabinet = '{$cabinet}'";
		$res = $this->db->Query( $strSql );
		$this->dictionary = [];

		while ( $row = $res->Fetch() ){
			$this->dictionary[ $row['bitrix_id'] ] = $row['chrtid'];
		}
	}

  public function getItems(){
    $arSelect = Array("ID","IBLOCK_ID","PROPERTY_AEN2","PROPERTY_CML2_ARTICLE", "IBLOCK_SECTION_ID");

    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
			"PROPERTY_CML2_ARTICLE" => $this->wbTop
    );
		// if ( $_REQUEST["CABINET"] == 'WT' ){
		// 	$arFilter['PROPERTY_CML2_ARTICLE'] = ['A-158WA-1'];
		// }

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		$this->arLog['NOT_BARCODE']['COUNT'] = 0;
		$this->items = array();
		$topItemsErrorCount = [];
		$log = [];
    while ($el = $result->GetNext()){
		  if (empty($el['PROPERTY_AEN2_VALUE']) || $el['PROPERTY_AEN2_VALUE'] == '') {
				$this->arLog['NOT_BARCODE']['COUNT'] = $this->arLog['NOT_BARCODE']['COUNT'] + 1;
				$this->arLog['NOT_BARCODE']['ITEMS'][] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
				$log['barcode'][] = $el['ID'];
			} else {
				if ( empty($this->dictionary[ $el['ID'] ]) ){
					// print_r( "{$el['PROPERTY_CML2_ARTICLE_VALUE']} has no chrtId match\n" );
					$log['chrtid'][] = $el['ID'];
					$topItemsErrorCount[] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
					continue;
				}
				$this->items[$el['ID']] = [
					'ID' => $el['ID'],
					'ARTICLE' => $el['PROPERTY_CML2_ARTICLE_VALUE'],
					'BARCODE' => $el['PROPERTY_AEN2_VALUE'],
					'CHRT_ID' => (int)$this->dictionary[ $el['ID'] ],
					'SEBES' => intval($this->arSebes[$el['PROPERTY_CML2_ARTICLE_VALUE']]),
					'SECTION_ID' => $el["IBLOCK_SECTION_ID"],
				];
				$this->barcodes[ $el['PROPERTY_AEN2_VALUE'] ] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
			}
    }
		var_dump('----------------------------');
		var_dump( count($log['barcode'] ?? []) );
		var_dump( count($log['chrtid'] ?? []) );
		$this->arLog['NOT_BARCODE']['ITEMS'] = json_encode($this->arLog['NOT_BARCODE']['ITEMS']);
		// var_dump( count($topItemsErrorCount) );
		file_put_contents(
			"/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/noChrtID.json",
			json_encode($topItemsErrorCount)
		);
	}

	public function getAll(){
		$arSelect = Array("ID","IBLOCK_ID", "IBLOCK_SECTION_ID", "PROPERTY_AEN2","PROPERTY_CML2_ARTICLE");

		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			"ACTIVE" => "Y",
			"!PROPERTY_AEN2" => false,
		);

		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		$itemsErrorCount = [];
		while ($el = $result->GetNext()){
			if ( empty($this->dictionary[ $el['ID'] ]) ){
				// print_r( "{$el['PROPERTY_CML2_ARTICLE_VALUE']} has no chrtId match\n" );
				$itemsErrorCount[] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
				continue;
			}
			$this->allItems[$el['ID']] = [
				'ID' => $el['ID'],
				'ARTICLE' => $el['PROPERTY_CML2_ARTICLE_VALUE'],
				'BARCODE' => $el['PROPERTY_AEN2_VALUE'],
				'CHRT_ID' => (int)$this->dictionary[ $el['ID'] ],
				'SECTION_ID' => $el["IBLOCK_SECTION_ID"],
			];
		}
		var_dump( count($itemsErrorCount) );
	}

	private function buildArray()
	{
		$sdp = new StocksDataProvider2( panel: $this->CurDB, main: \Bitrix\Main\Application::getConnection() );

		$activeItems = $sdp->getActiveItems( $_REQUEST['CABINET'] );
		$fboStock = $sdp->getFboStock( $_REQUEST['CABINET'] );
		$stockSuppliers = $sdp->getStockSuppliers();

		$sectionsDictionary = $this->getSectionDictionary();
		$exclude = [];
		if ( is_string($this->settings['exclude']) ){
			$exclude = explode(',', $this->settings['exclude']);
			$exclude = array_filter( $exclude );
			$exclude = array_map( 'trim', $exclude );
		}

		$result = [];

		$log = [
			'inactive' => 0,
			'fbo' => 0,
			'cost' => 0,
			'exclude' => 0,
			'sections' => 0,
			'approved' => 0,
		];

		$postive = [];

		foreach ( $this->allItems as $bitrix_id => $data ){
			$model = $data['ARTICLE'];
			$supplier = $activeItems[$bitrix_id]['supplier'];

			$min = (int) $this->settings['minSebes'];
			$max = (int) $this->settings['maxSebes'];
			$cost = (int) $activeItems[$bitrix_id]['cost'];

			$amount = 22;

			if ( !isset($activeItems[$bitrix_id]) ){
				$amount = 0;
				$log['inactive']++;
			}

			// if ( isset($fboStock[$model]) && !in_array($supplier, $stockSuppliers) ){
			// 	$amount = 0;
			// 	$log['fbo']++;
			// }

			if ( isset($fboStock[$model]) ){
				$amount = 0;
				$log['fbo']++;
			}

			if ( $min > $cost || $cost > $max ){
				$amount = 0;
				$log['cost']++;
			}

			if ( in_array($model, $exclude) ){
				$amount = 0;
				$log['exclude']++;
			}

			if ( $sectionsDictionary[$data['SECTION_ID']] != 932 ){
				$amount = 0;
				$log['sections']++;
			}

			if ( $amount > 0 ) {
				$log['approved']++;
				$positive[ $data['ARTICLE'] ] = true;
			}

			$result[] = [ "chrtId" => $data['CHRT_ID'], "amount" => $amount ];
		}
		var_dump( '-----------------' );
		var_dump( "activeItems: " . count($activeItems ?? []) );
		var_dump( "result: " . count($result ?? []) );
		var_dump( $log );

		file_put_contents(
			"{$_SERVER["DOCUMENT_ROOT"]}/admin/panel/engine/wb/logs/stock.json",
			json_encode( $positive )
		);

		$this->dataArray = array_chunk($result,1000);
	}

	public function buildArrayA(){
		$this->arLog['AMOUNT']['COUNT'] = 0;
		$this->arLog['ZERO']['COUNT'] = 0;
		$this->arLog['CHECK_FBO']['COUNT'] = 0;
		$this->dataArray = array();
		$sectionsDictionary = $this->getSectionDictionary();

		$exclude = [];
		if ( is_string($this->settings['exclude']) ){
			$exclude = explode(',', $this->settings['exclude']);
			$exclude = array_filter( $exclude );
			$exclude = array_map( 'trim', $exclude );
		}
			$log = [];
		 foreach ($this->items as $key => $value) {
			 	$sebes = intval($value['SEBES']);
				$min = intval($this->settings['minSebes']);
				$max = intval($this->settings['maxSebes']);
				// if ( $value['BARCODE'] == '2029029785628' ){
				// 	$stocks[] = [
				// 		"chrtId" => $value['CHRT_ID'],
				// 		"amount" => 0
				// 	];
				// 	continue;
				// }

				if (isset($this->stayZero[$value['ID']])) {

					$stocks[] = [
						"chrtId" => $value['CHRT_ID'],
						"amount" => 0
					];
					$log['inactive'][] = $value['ARTICLE'];
					if ( $value['ARTICLE'] == 'GD-350-1B' ){
						var_dump('here 1');
					}
				}
				// else if (isset($this->checkFbo[$value['ARTICLE']])) {
				// 	$stocks[] = [
				// 		"sku" => $value['BARCODE'],
				// 		"amount" => 0
				// 	];
				// 	$this->arLog['CHECK_FBO']['COUNT'] = $this->arLog['CHECK_FBO']['COUNT'] + 1;
				// 	$this->arLog['CHECK_FBO']['ITEMS'][] = $value['ARTICLE'];
				// }
				else if ( in_array($value['ARTICLE'], $exclude) ) {
					$stocks[] = [
						"chrtId" => $value['CHRT_ID'],
						"amount" => 0
					];
					$log['exclude'][] = $value['ARTICLE'];
					if ( $value['ARTICLE'] == 'GD-350-1B' ){
						var_dump('here 2');
					}
				}
				else if ( $sectionsDictionary[$value['SECTION_ID']] != 932 ) {
					$stocks[] = [
						"chrtId" => $value['CHRT_ID'],
						"amount" => 0
					];
					$log['section'][] = $value['ARTICLE'];
					if ( $value['ARTICLE'] == 'GD-350-1B' ){
						var_dump('here 2');
					}
				}
				else if ($sebes >= $min && $sebes <= $max) {
					$stocks[] = [
						"chrtId" => $value['CHRT_ID'],
						"amount" => 22
					];
					$this->arLog['AMOUNT']['COUNT'] = $this->arLog['AMOUNT']['COUNT'] + 1;
					$this->arLog['AMOUNT']['ITEMS'][] = $value['ARTICLE'];
				} else {
					$stocks[] = [
						"chrtId" => $value['CHRT_ID'],
						"amount" => 0
					];
					$log['cost'][] = $value['ARTICLE'];
					if ( $value['ARTICLE'] == 'GD-350-1B' ){
						var_dump('here 3');
					}
					$this->arLog['ZERO']['COUNT'] = $this->arLog['ZERO']['COUNT'] + 1;
					$this->arLog['ZERO']['ITEMS'][] = $value['ARTICLE'];
				}
				unset($value);
		 }
		 var_dump( count($this->items ?? []) );
		 var_dump( count($log['exclude'] ?? []) );
		 var_dump( count($log['inactive'] ?? []) );
		 var_dump( count($log['section'] ?? []) );
		 var_dump( count($log['cost'] ?? []) );
		 //fix
		 foreach ($this->allItems as $key => $value) {
		 	if (!isset($this->items[$value['ID']])) {
				$stocks[] = [
					"chrtId" => $value['CHRT_ID'],
					"amount" => 0
				];
				if ( $value['ARTICLE'] == 'GD-350-1B' ){
					var_dump('here 4');
				}
			}
		 }

		 $this->arLog['CHECK_FBO']['ITEMS'] = json_encode($this->arLog['CHECK_FBO']['ITEMS']);
		 $this->arLog['ZERO']['ITEMS'] = json_encode($this->arLog['ZERO']['ITEMS']);
		 $this->arLog['AMOUNT']['ITEMS'] = json_encode($this->arLog['AMOUNT']['ITEMS']);

		 $this->dataArray = array_chunk($stocks,1000);
		 //print_r($dataArray);
		 // die;
	}



	public function getWBTop() {
		$strSql = "SELECT * FROM ci_wb_top";
		$arIDs = array();
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$tops[$row["bitrix_id"]] = $row["article"];
		}
		var_dump( count($tops) );
		if ( $_REQUEST['CABINET'] == 'WR' ){
			$tops = [];
			$strSql = "SELECT * FROM ci_price WHERE active_wb = 'Y' AND supplier_id NOT IN (74)";
			$result = $this->db->Query( $strSql );
			while ( $row = $result->Fetch() ){
				$tops[ $row['bitrix_id'] ] = $row['model'];
			}
		}
		return $tops;
	}

	public function checkReserv() {
		$strSql = "SELECT * FROM ci_reserved";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		$this->arLog['RESERVED']['COUNT'] = 0;
		while ($row = $results->Fetch()){
			if(($row["RESERVED"] >= $row["AVAILABLE_RU"]) && isset($this->wbTop[$row["PRODUCT_ID"]])) {
				$this->arLog['RESERVED']['COUNT'] = $this->arLog['RESERVED']['COUNT'] + 1;
				$this->arLog['RESERVED']['ITEMS'][] = $this->wbTop[$row["PRODUCT_ID"]];
				$this->stayZero[$row["PRODUCT_ID"]] = $this->wbTop[$row["PRODUCT_ID"]];
				// unset($this->wbTop[$row["PRODUCT_ID"]]);
			}
		}
		$this->arLog['RESERVED']['ITEMS'] = json_encode($this->arLog['RESERVED']['ITEMS']);
	}

	public function checkQuarantine() {
		$arIDs = array_keys($this->wbTop);
		$strSql = "SELECT * FROM ci_price_quarantine WHERE PRODUCT_ID IN ('".implode("','", $arIDs)."') AND (SITE_ID = 'wb' OR PRICE_ID = 'WB')";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		$this->arLog['QUARANTINE']['COUNT'] = 0;
		while ($row = $results->Fetch()){
			$this->arLog['QUARANTINE']['COUNT'] = $this->arLog['QUARANTINE']['COUNT'] + 1;
			$this->arLog['QUARANTINE']['ITEMS'][] = $this->wbTop[$row["PRODUCT_ID"]];
			$this->stayZero[$row["PRODUCT_ID"]] = $this->wbTop[$row["PRODUCT_ID"]];
			// unset($this->wbTop[$row["PRODUCT_ID"]]);
		}
		$this->arLog['QUARANTINE']['ITEMS'] = json_encode($this->arLog['QUARANTINE']['ITEMS']);
	}

	public function checkFboStock($cab) {
		$this->checkFbo = [];
		if ($cab == 'WR') {
			$strSql = "SELECT * FROM wdhs_wb_fbo_stock";
			$resultDB = $this->db->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $resultDB->Fetch()){
				$this->checkFbo[$row['article']] = $row['article'];
			}
		} elseif ($cab == 'TL') {
			$strSql = "SELECT * FROM wdhs_wb_fbo_stock_TL";
			$resultDB = $this->db->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $resultDB->Fetch()){
				$this->checkFbo[$row['article']] = $row['article'];
			}
		}
	}

	public function getSebes($cab){
		$this->arSebes = array();
		if ( $cab == 'WR' ){
			$strSql = "SELECT * FROM `ci_price` WHERE `active_wb` = 'Y' AND supplier_id NOT IN (74)";
		} elseif ( $cab == 'WT' ) {
				$strSql = "SELECT * FROM `ci_price` WHERE `active_wb` = 'Y'";
		} else if ($cab == 'TL') {
				$strSql = "SELECT * FROM `ci_price` WHERE `active_wbtl` = 'Y'";
		} else {
			$this->arLog['CRITICAL_ERROR'][] = 'Проблемы с получением себестоймости моделей из ci_price';
			die('Проблемы с получением себестоймости моделей из ci_price');
		}
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()){
			$arSebesTmp[$row["model"]][] = $row;
		}

		$strSql = "SELECT * FROM `ci_reserved`";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()){
			$arReserved[$row["ARTICLE"]] = $row["RESERVED_s1"];
		}


		foreach ($arSebesTmp as $model => &$items) {

    usort($items, function($a, $b) {
        return $a['price'] <=> $b['price'];
    });




    foreach ($items as $item) {
        $reserved = $arReserved[$model] ?? 0;
        $count = $item['count'] ?? 0;

		        if ($reserved - $count >= 0) {
		            $arReserved[$model] -= $count;
						} else {
		            $this->arSebes[$model] = $item['price'];
								break;
						}
        }
			}

		unset($items);


		// foreach($arSebesTmp as $key => $values) {
		// 	$this->arSebes[$key] = min($values);
		// }
		// unset($arSebesTmp);
	}

	public function getWH(){
		$res = $this->sendRequest('https://marketplace-api.wildberries.ru/api/v3/warehouses','GET');
		// var_dump($res);
		if (count($res) > 0) {
			return $res[0];
		} else {
			$this->arLog['CRITICAL_ERROR'][] = 'Проблемы с получением склада от WB';
			die('Проблемы с получением склада от WB');
		}
	}

	public function checkActive($cab){
		if ( $cab == 'WR' ){
			$strSql = "SELECT * FROM `ci_price` WHERE `active_wb` = 'Y' AND supplier_id NOT IN (74) GROUP BY `model`";
		} elseif ( $cab == 'WT' ) {
				$strSql = "SELECT * FROM `ci_price` WHERE `active_wb` = 'Y' GROUP BY `model`";
		} else if ($cab == 'TL'){
			 	$strSql = "SELECT * FROM `ci_price` WHERE `active_wbtl` = 'Y' GROUP BY `model`";
		} else {
			$this->arLog['CRITICAL_ERROR'][] = 'Проблемы с получением активных моделей из ci_price';
			die('Проблемы с получением активных моделей из ci_price');
		}

		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()){
			$active[$row["model"]] = $row["model"];
		}
		$this->arLog['NOT_ACTIVE']['COUNT'] = 0;
		foreach ($this->wbTop as $key => $value) {
			if (!in_array($value,$active)) {
				$this->arLog['NOT_ACTIVE']['COUNT'] = $this->arLog['NOT_ACTIVE']['COUNT'] + 1;
				$this->arLog['NOT_ACTIVE']['ITEMS'][] = $this->wbTop[$key];
				$this->stayZero[$key] = $this->wbTop[$key];
				// unset($this->wbTop[$key]);
			}
		}
		// file_put_contents(
		// 	'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/stayZero.txt',
		// 	print_r( $this->stayZero, true )
		// );
		// die;
		$this->arLog['NOT_ACTIVE']['ITEMS'] = json_encode($this->arLog['NOT_ACTIVE']['ITEMS']);
	}

	public function sendStock(){
		foreach ($this->dataArray as $key => $value) {
			file_put_contents(
				'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/importStockBody.txt',
				print_r( $value, 1 )
			);
			$res = $this->sendRequest('https://marketplace-api.wildberries.ru/api/v3/stocks/'.$this->wh['id'],'PUT', array('stocks' => $value));
			file_put_contents(
				'/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/importStockRes.txt',
				print_r( $res, 1 )
			);
			$this->arLogSend[] = $res;
			sleep( 1 );
		}
	}

	public function checkConnection(){
		$check = $this->sendRequest('https://content-api.wildberries.ru/ping','GET');
		if (isset($check['Status']) && $check['Status'] == 'OK') {
			return true;
		} else if (isset($check['status']) && $check['status'] == '401') {
			$this->arLog['CRITICAL_ERROR'][] = 'Проблема с ключом API';
			$this->bot->sendMessage("Проблема с ключом API {$_REQUEST['CABINET']}");
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

	private function checkStockDifference():void
	{
		$cab = $_REQUEST['CABINET'];
		$module = "wb_importStock_{$cab}";
		$nameTemplateSys = "importStock_{$cab}";
		$nameTemplateBot = "Остатки_WB_{$cab}";
		$messageHeader = "<b>Модуль выгрузки остатков WB {$cab}:</b>\n\n";

		$filenamePrev = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/export/{$nameTemplateSys}_prev.xlsx";
		$filenameLast = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/export/{$nameTemplateSys}_last.xlsx";

		$row = $this->CurDB->select(['*'], 'modules_control')->where('module', $module)->make();
		$oldValue = intval( $row[0]['old_value'] );
		$newValue = intval( $row[0]['new_value'] );
		$control = 0;
		$dataForBot = [];

		foreach ( $this->dataArray as $value ){
			foreach ($value as $elem ){
				if ( $elem['amount'] > 0 ) $control++;
				if ( empty($this->barcodes[ $elem['sku'] ]) ) continue;
				$dataForBot[] = [ $this->barcodes[ $elem['sku'] ], $elem['amount'] ];
			}
		}

		if ( $newValue != 0 ){
			$diff = ($newValue - $control) / $newValue * 100;
		}else{
			$diff = 0;
		}

		$diffInfo = abs( round($diff, 2) );

		if ( $diff > 0 ){
			$cWord = 'меньше';
		}
		else{
			$cWord = 'больше';
		}

		if ( $diffInfo > intval($this->settings['threshold']) ){
			$this->bot->sendMessage("{$messageHeader}<b>⚠ Выгружено на <u>{$diffInfo}%</u> {$cWord} положительных остатков!</b>\n\n✅ <b>Прошлая итерация: <u>{$newValue}</u></b>.\n❌ <b>Текущая итерация: <u>{$control}</u></b>\n\n<i>Убедитесь в отсутствии ошибок!</i>\n<i>Ниже прикреплены файлы с артикулами выгрузок</i>");
			if ( file_exists($filenamePrev) ){
				$this->bot->sendFile($filenamePrev, "{$nameTemplateBot}_prev.xlsx");
			}
		}

		if ( file_exists($filenameLast) ) rename($filenameLast, $filenamePrev);

		$newFile = $this->buildXlsx( 'wb', "{$nameTemplateSys}_last", ['Модель', 'Остаток'], $dataForBot );
		if ( $diffInfo > intval($this->settings['threshold']) ){
			if ( $newFile ){
				$res = $this->bot->sendFile($newFile, "{$nameTemplateBot}_last.xlsx");
				// var_dump($res);
			}else{
				$this->bot->sendMessage("Файл {$nameTemplateBot}_last.xlsx не может быть отправлен ввиду непредвиденной ошибки: массив пустой");
			}
		}

		if ( $newValue == 0 && $control == 0 ){
			$this->bot->sendMessage("<b>{$messageHeader}❌Нет положительных остатков в двух или более итерациях подряд</b>\n\n⚠<i>Необходимо срочно исправить ошибку!</i>");
		}
		$date = date('Y-m-d G:i:s');
		$strSql = " UPDATE modules_control SET old_value = '{$newValue}', new_value = '{$control}', date = '{$date}' WHERE module = '{$module}'";
		$this->CurDB->query( $strSql );
	}

	private function buildXlsx( string $module, string $name, array $headers, array $data ):string
	{
		if (!class_exists('SpreadsheetReader')){
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
		  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}

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

	private function getSectionDictionaryA()
	{
	  $res = CIBlockSection::GetList(
	      ['LEFT_MARGIN' => 'ASC'],
	      ['IBLOCK_ID' => 16, 'ACTIVE' => 'Y'],
	      false,
	      ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'DEPTH_LEVEL']
	  );

	  $sections = [];
	  $rootMap = []; // Храним корень для каждого раздела
	  $parentStack = []; // Стек родителей для определения корня

	  while ($section = $res->Fetch()) {
	      $sectionId = $section['ID'];
	      $parentId = $section['IBLOCK_SECTION_ID'];
	      $depth = $section['DEPTH_LEVEL'];

	      $sections[$sectionId] = $section;

	      // Определяем корневой раздел для текущего
	      if ($depth == 1) {
	          $rootMap[$sectionId] = $sectionId; // Сам себе корень
	          $parentStack[$depth] = $sectionId;
	      } else {
	          // Корень такой же, как у родителя
	          $rootMap[$sectionId] = $rootMap[$parentId];
	          $parentStack[$depth] = $sectionId;
	      }
	  }

	  // Находим самые глубокие разделы (не имеющие детей)
	  $hasChildren = array_fill_keys(array_keys($sections), false);
	  foreach ($sections as $sectionId => $section) {
	      $parentId = $section['IBLOCK_SECTION_ID'];
	      if ($parentId > 0) {
	          $hasChildren[$parentId] = true;
	      }
	  }

	  // Формируем итоговый словарь
	  $deepToRoot = [];
	  foreach ($sections as $sectionId => $section) {
	      if (!$hasChildren[$sectionId]) {
	          $deepToRoot[$sectionId] = $rootMap[$sectionId];
	      }
	  }

	  return $deepToRoot;
	}

	function getSectionDictionary():array
	{
	  $res = CIBlockSection::GetList(
	      ['DEPTH_LEVEL' => 'ASC'],
	      ['IBLOCK_ID' => 16, 'ACTIVE' => 'Y'],
	      false,
	      ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'DEPTH_LEVEL']
	  );

	  $result = [];
	  $brands = [];

	  while( $row = $res->getNext() )
	  {
	    if ( $row['DEPTH_LEVEL'] == 1 ) continue;
	    if ( $row['DEPTH_LEVEL'] == 2 ){
	      $brands[ $row['ID'] ] = $row['IBLOCK_SECTION_ID'];
	      $result[ $row['ID'] ] = $row['IBLOCK_SECTION_ID'];
	    }
	    if ( $row['DEPTH_LEVEL'] == 3 ){
	      $result[ $row['ID'] ] = $brands[ $row['IBLOCK_SECTION_ID'] ];
	    }
	  }

	  return $result;
	}

}


(new WbImportStocks())->run();
$workers->updateStatus("N");
