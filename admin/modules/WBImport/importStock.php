<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class WbImportStocks{
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

		$this->arLog['TIME_START'] = date('H:i:s');
		$this->arLogSend['TIME_START'] = date('H:i:s');
		if(isset($this->arSetting[$_REQUEST["CABINET"]])){
			$this->clientId = $this->arSetting[$_REQUEST["CABINET"]]['clientId'];
			$this->token = $this->arSetting[$_REQUEST["CABINET"]]['api'];
			$this->settings = json_decode($this->arSetting[$_REQUEST["CABINET"]]['settings'],true);

			if (!$this->settings['upload_stock']) die('upload_stock = 0');

			$checkConnection = $this->checkConnection();
			if (!$checkConnection) die('connection false');

			$this->wh = $this->getWH();
			$this->wbTop = $this->getWBTop();

			if (!is_array($this->wbTop)) {
				$this->arLog['CRITICAL_ERROR'][] = 'ТОП WB пуст!';
				die('ТОП WB пуст!');
			}

			$this->arLog['WB_TOP']['COUNT'] = count($this->wbTop);
			$this->arLog['WB_TOP']['ITEMS'] = json_encode($this->wbTop);
			//Проверки
			$this->checkReserv();
			$this->checkQuarantine();
			$this->checkFboStock($_REQUEST["CABINET"]);
			$this->checkActive($_REQUEST["CABINET"]);

			//Получение из БТ
			$this->getSebes($_REQUEST["CABINET"]);
			$this->getItems();
			$this->getAll();

			//подготовка к загрузке
			if (count($this->items) == 0) {
				$this->arLog['CRITICAL_ERROR'][] = 'Пустой массив при выборке из Битрикса';
				die('Пустой массив при выборке из Битрикса');
			}

			$this->buildArray();
			if (count($this->dataArray) == 0) {
				$this->arLog['CRITICAL_ERROR'][] = 'Пустой массив для отправки на ВБ';
				die('Пустой массив для отправки на ВБ');
			}

			$this->sendStock();



		} else {
			$this->arLog['CRITICAL_ERROR'][] = 'Не действительный кабинет!';
			die('Не действительный кабинет!');
		}
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/reqests/stock/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r(json_encode($this->arLogSend), true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/reqests/stock/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r('#####', true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/stock/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/stock/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r('#####', true).PHP_EOL,FILE_APPEND);
			$this->db->Update("wdhs_wb_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='stock' AND cabinet = '".$_REQUEST["CABINET"]."'", $err_mess.__LINE__);
	}

  public function getItems(){
    $arSelect = Array("ID","IBLOCK_ID","PROPERTY_AEN2","PROPERTY_CML2_ARTICLE");

    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
			"PROPERTY_CML2_ARTICLE" => $this->wbTop
    );

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		$this->arLog['NOT_BARCODE']['COUNT'] = 0;
		$this->items = array();
    while ($el = $result->GetNext()){
		  if (empty($el['PROPERTY_AEN2_VALUE']) || $el['PROPERTY_AEN2_VALUE'] == '') {
				$this->arLog['NOT_BARCODE']['COUNT'] = $this->arLog['NOT_BARCODE']['COUNT'] + 1;
				$this->arLog['NOT_BARCODE']['ITEMS'][] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
			} else {
				$this->items[$el['ID']] = [
					'ID' => $el['ID'],
					'ARTICLE' => $el['PROPERTY_CML2_ARTICLE_VALUE'],
					'BARCODE' => $el['PROPERTY_AEN2_VALUE'],
					'SEBES' => intval($this->arSebes[$el['PROPERTY_CML2_ARTICLE_VALUE']])
				];
			}
    }
		$this->arLog['NOT_BARCODE']['ITEMS'] = json_encode($this->arLog['NOT_BARCODE']['ITEMS']);
	}
	public function getAll(){
		$arSelect = Array("ID","IBLOCK_ID","PROPERTY_AEN2","PROPERTY_CML2_ARTICLE");

		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			"ACTIVE" => "Y",
			"!PROPERTY_AEN2" => false,
		);

		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		while ($el = $result->GetNext()){
				$this->allItems[$el['ID']] = [
					'ID' => $el['ID'],
					'ARTICLE' => $el['PROPERTY_CML2_ARTICLE_VALUE'],
					'BARCODE' => $el['PROPERTY_AEN2_VALUE'],
				];
		}
	}

	public function buildArray(){
		$this->arLog['AMOUNT']['COUNT'] = 0;
		$this->arLog['Z)ERO']['COUNT'] = 0;
		$this->arLog['CHECK_FBO']['COUNT'] = 0;
		$this->dataArray = array();
		 foreach ($this->items as $key => $value) {
			 	$sebes = intval($value['SEBES']);
				$min = intval($this->settings['minSebes']);
				$max = intval($this->settings['maxSebes']);
				if (isset($this->checkFbo[$value['ARTICLE']])) {
					$stocks[] = [
						"sku" => $value['BARCODE'],
						"amount" => 0
					];
					$this->arLog['CHECK_FBO']['COUNT'] = $this->arLog['CHECK_FBO']['COUNT'] + 1;
					$this->arLog['CHECK_FBO']['ITEMS'][] = $value['ARTICLE'];
				} else if ($sebes >= $min && $sebes <= $max) {
					$stocks[] = [
						"sku" => $value['BARCODE'],
						"amount" => 23
					];
					$this->arLog['AMOUNT']['COUNT'] = $this->arLog['AMOUNT']['COUNT'] + 1;
					$this->arLog['AMOUNT']['ITEMS'][] = $value['ARTICLE'];
				} else {
					$stocks[] = [
						"sku" => $value['BARCODE'],
						"amount" => 0
					];
					$this->arLog['ZERO']['COUNT'] = $this->arLog['ZERO']['COUNT'] + 1;
					$this->arLog['ZERO']['ITEMS'][] = $value['ARTICLE'];
				}
				unset($value);
		 }
		 //fix
		 foreach ($this->allItems as $key => $value) {
		 	if (!isset($this->items[$value['ID']])) {
				$stocks[] = [
					"sku" => $value['BARCODE'],
					"amount" => 0
				];
			}
		 }

		 $this->arLog['CHECK_FBO']['ITEMS'] = json_encode($this->arLog['CHECK_FBO']['ITEMS']);
		 $this->arLog['ZERO']['ITEMS'] = json_encode($this->arLog['ZERO']['ITEMS']);
		 $this->arLog['AMOUNT']['ITEMS'] = json_encode($this->arLog['AMOUNT']['ITEMS']);

		 $this->dataArray = array_chunk($stocks,1000);
		 //print_r($dataArray);
	}



	public function getWBTop() {
		$strSql = "SELECT * FROM ci_wb_top";
		$arIDs = array();
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$tops[$row["bitrix_id"]] = $row["article"];
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
				unset($this->wbTop[$row["PRODUCT_ID"]]);
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
			unset($this->wbTop[$row["PRODUCT_ID"]]);
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
		}
	}

	public function getSebes($cab){
		$this->arSebes = array();
		if ($cab == 'WR') {
				$strSql = "SELECT * FROM `ci_price` WHERE `active_wb` = 'Y'";
		} else if ($cab == 'TL') {
				$strSql = "SELECT * FROM `ci_price` WHERE `active_wbtl` = 'Y'";
		} else {
			$this->arLog['CRITICAL_ERROR'][] = 'Проблемы с получением себестоймости моделей из ci_price';
			die('Проблемы с получением себестоймости моделей из ci_price');
		}

		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()){
			$arSebesTmp[$row["model"]][] = $row["price"];
		}

		foreach($arSebesTmp as $key => $values) {
			$this->arSebes[$key] = min($values);
		}
		unset($arSebesTmp);
	}

	public function getWH(){
		$res = $this->sendRequest('https://marketplace-api.wildberries.ru/api/v3/warehouses','GET');
		if (count($res) > 0) {
			return $res[0];
		} else {
			$this->arLog['CRITICAL_ERROR'][] = 'Проблемы с получением склада от WB';
			die('Проблемы с получением склада от WB');
		}
	}

	public function checkActive($cab){
		if ($cab == 'WR') {
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
				unset($this->wbTop[$key]);
			}
		}
		$this->arLog['NOT_ACTIVE']['ITEMS'] = json_encode($this->arLog['NOT_ACTIVE']['ITEMS']);
	}

	public function sendStock(){
		foreach ($this->dataArray as $key => $value) {
			$res = $this->sendRequest('https://marketplace-api.wildberries.ru/api/v3/stocks/'.$this->wh['id'],'PUT', array('stocks' => $value));
			$this->arLogSend[] = $res;
		}
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
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HEADER, false);
			$res = curl_exec($ch);
			curl_close($ch);

			$res = json_decode($res, true);

			return $res;
	}

}


(new WbImportStocks())->run();
