<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
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

			$this->arLog['TIME_START'] = date('H:i:s');
			$this->arLogSend['TIME_START'] = date('H:i:s');
			if(isset($this->arSetting[$_REQUEST["CABINET"]])){
				$this->clientId = $this->arSetting[$_REQUEST["CABINET"]]['clientId'];
				$this->token = $this->arSetting[$_REQUEST["CABINET"]]['api'];
				$this->settings = json_decode($this->arSetting[$_REQUEST["CABINET"]]['settings'],true);

				if (!$this->settings['upload_price']) die('upload_price = 0');

				$checkConnection = $this->checkConnection();
				if (!$checkConnection) die('connection false');

				$this->wbTop = $this->getWBTop();

				if (!is_array($this->wbTop)) {
					$this->arLog['CRITICAL_ERROR'][] = 'ТОП WB пуст!';
					die('ТОП WB пуст!');
				}

				$this->arLog['WB_TOP']['COUNT'] = count($this->wbTop);
				$this->arLog['WB_TOP']['ITEMS'] = json_encode($this->wbTop);
				//Проверки
				$this->checkQuarantine();
				$this->checkFBO($_REQUEST["CABINET"]);
				$this->getNMID($_REQUEST["CABINET"]);

				//Получение из БТ
				$this->getItems($_REQUEST["CABINET"]);

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

				$this->sendPrice();
			} else {
				$this->arLog['CRITICAL_ERROR'][] = 'Не действительный кабинет!';
				die('Не действительный кабинет!');
			}
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/reqests/prices/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r(json_encode($this->arLogSend), true).PHP_EOL,FILE_APPEND);
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/reqests/prices/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r('#####', true).PHP_EOL,FILE_APPEND);
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/prices/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r(json_encode($this->arLog), true).PHP_EOL,FILE_APPEND);
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/prices/{$_REQUEST["CABINET"]}/{$this->date}.txt", print_r('#####', true).PHP_EOL,FILE_APPEND);
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/test.txt", print_r($el, true).PHP_EOL,FILE_APPEND);

			$this->db->Update("wdhs_wb_upload_status", array("status" => "'COMPLETE'","percent" => "'100'",'time' => "'". date("H:i:s") ."'"), "WHERE agent='price' AND cabinet = '".$_REQUEST["CABINET"]."'", $err_mess.__LINE__);
		}

		public function getItems($cab){
			if ($cab == 'WR') {
					$priceIndex = 'WBPRICE';
			} else if ($cab == 'TL') {
					$priceIndex = 'WBTL_PRICE';
			}

	    $arSelect = Array("ID","IBLOCK_ID","PROPERTY_AEN2","PROPERTY_CML2_ARTICLE","PROPERTY_".$priceIndex."");


	    $arFilter = Array(
	      "IBLOCK_ID" => CProSet::IB_CATALOG,
				"PROPERTY_CML2_ARTICLE" => $this->wbTop
	    );

	    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
			$this->arLog['NOT_NMID']['COUNT'] = 0;
			$this->arLog['NOT_PRICE']['COUNT'] = 0;
			$this->arLog['FBO']['COUNT'] = 0;
			$this->arLog['FBO']['ITEMS'] = [];
			$this->items = array();
	    while ($el = $result->GetNext()){

			  if (empty($el["PROPERTY_".$priceIndex."_VALUE"]) || $el["PROPERTY_".$priceIndex."_VALUE"] == '') {
					$this->arLog['NOT_PRICE']['COUNT'] = $this->arLog['NOT_PRICE']['COUNT'] + 1;
					$this->arLog['NOT_PRICE']['ITEMS'][] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
				} else if (!isset($this->arNMID[$el["PROPERTY_CML2_ARTICLE_VALUE"]]) || $this->arNMID[$el["PROPERTY_CML2_ARTICLE_VALUE"]] == '') {
					$this->arLog['NOT_NMID']['COUNT'] = $this->arLog['NOT_PRICE']['COUNT'] + 1;
					$this->arLog['NOT_NMID']['ITEMS'][] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
				} else {
					if ($cab == 'WR') {
						if (isset($this->checkFBOPrice[$el["PROPERTY_CML2_ARTICLE_VALUE"]])) {
							$price = $this->checkFBOPrice[$el["PROPERTY_CML2_ARTICLE_VALUE"]];
							$this->arLog['FBO']['ITEMS'][] = $el['PROPERTY_CML2_ARTICLE_VALUE'];
							$this->arLog['FBO']['COUNT'] = $this->arLog['FBO']['COUNT'] + 1;
						} else {
							$price = $el["PROPERTY_".$priceIndex."_VALUE"];
						}
					} else {
						$price = $el["PROPERTY_".$priceIndex."_VALUE"];
					}
					$this->items[$el['ID']] = [
						'ID' => $el['ID'],
						'ARTICLE' => $el['PROPERTY_CML2_ARTICLE_VALUE'],
						'NMID' => $this->arNMID[$el["PROPERTY_CML2_ARTICLE_VALUE"]],
						'PRICE' => $el["PROPERTY_".$priceIndex."_VALUE"],
					];
				}
	    }
			$this->arLog['NOT_PRICE']['ITEMS'] = json_encode($this->arLog['NOT_PRICE']['ITEMS']);
			$this->arLog['NOT_NMID']['ITEMS'] = json_encode($this->arLog['NOT_NMID']['ITEMS']);
		}

		public function buildArray(){
			$this->arLog['AMOUNT']['COUNT'] = 0;
			 foreach ($this->items as $key => $value) {
						$prcies[] = [
							"nmID" => intval($value['NMID']),
							"price" => intval($value['PRICE']),
						];
						$this->arLog['AMOUNT']['COUNT'] = $this->arLog['AMOUNT']['COUNT'] + 1;
						$this->arLog['AMOUNT']['ITEMS'][] = ['article' => $value['ARTICLE'], 'price' => $value['PRICE']];
			 }
			 $this->arLog['AMOUNT']['ITEMS'] = json_encode($this->arLog['AMOUNT']['ITEMS']);

			 $this->dataArray = array_chunk($prcies,1000);

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

		public function checkFBO($cab) {
			if ($cab == 'WR') {
				$strSql = "SELECT * FROM wdhs_ozon_fbo_price";
				$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
				while ($row = $results->Fetch()){
					$this->checkFBOPrice[$row['article']] = $row['price'];
				}
			} else {
				$this->checkFBOPrice = array();
			}
		}

		public function getNMID($cab) {
			if ($cab == 'WR') {
					$strSql = "SELECT * FROM wdhs_wb_props WHERE `cabinet` = 'WR'";
			} else if ($cab == 'TL') {
					$strSql = "SELECT * FROM wdhs_wb_props WHERE `cabinet` = 'TL'";
			}
			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$this->arNMID[$row['article']] = $row['nmid'];
			}
		}

		public function sendPrice(){
			foreach ($this->dataArray as $key => $value) {
				//$request = 'https://discounts-prices-api.wb.ru/api/v2/upload/task';

				// $data_string = json_encode($data);
				// $ch = curl_init($request);
				//
				// curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
				// curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
				// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				// curl_setopt($ch, CURLOPT_HEADER, true);
				// curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				// 	'Content-Type: application/json',
				// 	'Content-Length: ' . strlen($data_string),
				// 	'Authorization: ' . $this->token,
				// ));
				// $res = curl_exec($ch);
				// curl_close($ch);
				//
				// $res = json_decode($res, true);

				$data = ['data' => $value];
				$res = $this->sendRequest('https://discounts-prices-api.wildberries.ru/api/v2/upload/task','POST', $data);
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
}

(new WbImportPrices())->run();
