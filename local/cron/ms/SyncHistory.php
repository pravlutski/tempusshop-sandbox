#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);
//if (function_exists('ini_set')) ini_set('memory_limit','1512M');

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class SyncHistory{
	public function __construct($site_id = "s1"){
		global $DB;
		$this->loadModules();
		$this->ms = new MoyskladAPI($site_id);
		$this->site_id = $site_id;
		$this->db = $DB;

		$this->logger = new TsLogger("/ms/" . __CLASS__ . "/");
		$this->triggers = new TsTriggers();
		$this->workers = new WorkersChecker(__CLASS__);
		$this->resms = [];

	}

	private function loadModules()
    {
		Loader::includeModule("panel.manager");
		Loader::includeModule("iblock");
        Loader::includeModule("catalog");
    }

	public function run(){

		//$context = Application::getInstance()->getContext();
		//$request = $context->getRequest();
		//$req = $request->getQueryList()->toArray();

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		if (!$this->workers->checkStatus()) {
			$this->logger->log("LOG", "Обработчик занят");
			exit();
		}

		$this->workers->updateStatus("Y");

		$needSyncProduct = (date("H") % 12 == 0 && date("i") >= 30 ? true : false);
		// синхронизируем полный список товаров
		if($request["syncProduct"] == "Y" || $needSyncProduct === true ){
		 	$this->syncProduct();
		 	$this->syncBarcodes();
		}
		$this->syncSalesChannel();

		$this->getAssortment();
		$this->getAgent();
		$this->getStore();

		$this->getHistory();

		$this->sendMS();

		$this->triggers->SendTriggerErrors();
		$this->workers->updateStatus("N");
	}
	public function syncProduct(){
		//$res = $this->ms->send("/entity/product/", "GET", [], ["Content-Type" => "application/json"], false, ["limit" => 10]);
		$res = $this->ms->send("/entity/product/", "GET", [], ["Content-Type" => "application/json"], true);
		$this->resms = $res;
		if(is_array($res) && count($res) > 0){
			CProSet::setOption("MS_LAST_UPDATE_PRODUCT", count($res));
		}
//file_put_contents("/home/bitrix/logs/supplyres.txt", "asd " . print_r($res, true));
		foreach($res as $k => $arItem){
			$arProduct[$arItem["externalCode"]] = array(
				"MS_ID" => $arItem["id"],
				"XML_ID" => $arItem["externalCode"],
				"NAME" => $arItem["name"],
			);
		}
		// ищем ID битрикса

		if(!$arProduct) return;

		$strSql = "SELECT ID, XML_ID FROM b_iblock_element WHERE XML_ID IN ('".implode("','", array_keys($arProduct))."') AND IBLOCK_ID = '16'";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arProduct[$row["XML_ID"]]["BX_ID"] = $row["ID"];
		}

		// очищаем товары и записываем заново
		$this->db->Query("DELETE FROM ci_ms_assortment WHERE SITE_ID = '{$this->site_id}'");

		foreach($arProduct as $arItem){
			$in = array(
				"MS_ID" => "'".addslashes($arItem["MS_ID"])."'",
				"XML_ID" => "'".addslashes($arItem["XML_ID"])."'",
				"BX_ID" => "'".addslashes($arItem["BX_ID"])."'",
				"NAME" => "'".addslashes($arItem["NAME"])."'",
				"SITE_ID" => "'".addslashes($this->site_id)."'",
			);
			//prent($in);
			$this->db->Insert("ci_ms_assortment", $in, $err_mess.__LINE__);
		}

	}

	public function syncSalesChannel(){
		$arSalesChannel = [];
		$res = $this->ms->send("/entity/saleschannel", "GET", [], ["Content-Type" => "application/json"], true);

		foreach($res as $k => $arItem){
			$arSalesChannel[$arItem["externalCode"]] = array(
				"MS_ID" => $arItem["id"],
				"NAME" => $arItem["name"],
				"TYPE" => $arItem["type"],
			);
		}

		if(!$arSalesChannel) return;

		// очищаем и записываем заново
		$this->db->Query("DELETE FROM ci_ms_saleschannel WHERE SITE_ID = '{$this->site_id}'");

		foreach($arSalesChannel as $arItem){
			$in = array(
				"MS_ID" => "'".addslashes($arItem["MS_ID"])."'",
				"NAME" => "'".addslashes($arItem["NAME"])."'",
				"TYPE" => "'".addslashes($arItem["TYPE"])."'",
				"SITE_ID" => "'".addslashes($this->site_id)."'",
			);
			$this->db->Insert("ci_ms_saleschannel", $in, $err_mess.__LINE__);
		}

	}

	private function syncBarcodes(){
		if ( $this->site_id != 's1' ) return false;
		$res = $this->resms;
		/*file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barlog.txt', print_r('--------',true).PHP_EOL, FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barlog.txt', print_r(date('Y-m-d H:i:s',true)).PHP_EOL, FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barlog.txt', print_r('($this->resms) Кол-во МС',true).PHP_EOL, FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barlog.txt', print_r(count($this->resms),true).PHP_EOL, FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barcodesMS.txt', print_r(date('Y-m-d H:i:s'),true).PHP_EOL, FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barcodesMS.txt', print_r(json_encode($res), true).PHP_EOL, FILE_APPEND);
		*/// $this->triggers->SetError(["Началась синхронизация бакркодов с МС"]);
	  // $this->triggers->SendTriggerErrors();
		if ( empty( $res ) ){
			$this->triggers->SetError(["Синхронизация баркодов: пустой массив"]);
		  $this->triggers->SendTriggerErrors();
			return false;
		}

		$arItems = [];
		$xml_ids = [];
		foreach ( $res as $product ){
			$arItems[ $product['code'] ] = [
				'xml_id' => $product['externalCode'],
			];
			$xml_ids[] = $product['externalCode'];
			foreach ( $product['barcodes'] as $barcode ) {
				foreach ( $barcode as $type ){
					if ( substr( $type, 0, 1 ) != 2 && is_numeric($type) ){
						$arItems[ $product['code'] ]['barcodes'][] = $type;
					}
				}
			}
		}
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barlog.txt', print_r('($arItems) Кол-во МС без баркодов с 2ойки',true).PHP_EOL, FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barlog.txt', print_r(count($arItems),true).PHP_EOL, FILE_APPEND);
		// var_dump( 'Got items from MS -- ' . count($arItems) );

		$arFilter = ["IBLOCK_ID" => 16, "=XML_ID" => $xml_ids];
		$arSelect = ["ID", "IBLOCK_ID", "XML_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_barcodes"];
		$res = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );

		$arImport = [];
		$arDebug = [];
		while ( $row = $res->GetNext() ){
			$arDebug[] = [
				"model" => $row['PROPERTY_CML2_ARTICLE_VALUE'],
				'xml_id' => $row['XML_ID'],
				'barcodes' => $row["PROPERTY_barcodes_VALUE"],
			];
			if ( stripos( $row['PROPERTY_BARCODES_VALUE'], ',' ) ){
				$barcodesRow = explode( ',', $row['PROPERTY_BARCODES_VALUE'] );
			}else{
				$barcodesRow = [ $row['PROPERTY_BARCODES_VALUE'] ];
			}

			$barcodesRow = array_map( function($item){
				return trim( $item );
			}, $barcodesRow );

			$barcodesRow = array_filter( $barcodesRow );
			$barcodesSTART = $barcodesRow;


			foreach ( $arItems[ $row['XML_ID'] ]['barcodes'] as $barcode ){
				if ( !in_array($barcode, $barcodesRow) ){
					$barcodesRow[] = $barcode;
				}
			}
			if ( !empty( array_diff($barcodesRow, $barcodesSTART) ) ){
				$arImport[ intval($row['ID']) ] = implode(', ', $barcodesRow);
				$arLogDiff[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ] = ['newstr' => implode(', ', $barcodesRow), 'oldstr' => $barcodesSTART];
			}
		}
		/*file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/LOGDIF.txt', print_r($arLogDiff,true).PHP_EOL, FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barlog.txt', print_r('($arDebug) Кол-во Битрикс',true).PHP_EOL, FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barlog.txt', print_r(count($arItems),true).PHP_EOL, FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barlog.txt', print_r('($arImport) Кол-во изменений',true).PHP_EOL, FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barlog.txt', print_r(count($arImport),true).PHP_EOL, FILE_APPEND);
		// var_dump( 'Will be imported -- ' . count($arImport) );
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barcodesBX.txt', print_r(json_encode($arDebug), true).PHP_EOL, FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barcodesImport.txt', print_r(json_encode($arImport), true).PHP_EOL, FILE_APPEND);
		*/
		foreach ( $arImport as $id => $barcodesStr ){
			$r1 = CIBlockElement::SetPropertyValueCode( $id, "2823", ['VALUE' => $barcodesStr] );
		}
		CProSet::setOption( 'SYNC_BARCODES', count($arImport) );
	}

	public function getAssortment(){
		$strSql = "SELECT * FROM ci_ms_assortment";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		$arAlternative = array();
		while ($row = $results->Fetch()){
			$this->assortment[$row["MS_ID"]] = array(
				"XML_ID" => $row["XML_ID"],
				"NAME" => $row["NAME"],
			);
		}
	}

	public function getAgent(){
		$strSql = "SELECT * FROM ci_ms_agent";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		$arAlternative = array();
		while ($row = $results->Fetch()){
			$this->agent[$row["UNIQUE_ID"]] = $row["NAME"];
			$this->agentID[$row["UNIQUE_ID"]] = $row["MS_ID"];

		}
	}

	public function getStore(){
		$strSql = "SELECT * FROM ci_ms_store";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		$arAlternative = array();
		while ($row = $results->Fetch()){
			$this->store[$row["UNIQUE_ID"]] = $row["NAME"];
			$this->storeID[$row["UNIQUE_ID"]] = $row["MS_ID"];

		}
	}

	public function getHistory(){
		$strSql = "SELECT * FROM ci_ms_history";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		$arAlternative = array();
		while ($row = $results->Fetch()){
			$this->history[$row["DOCUMENT_ID"]] = $row["CHECK_ID"];
		}
	}

	public function sendMS(){

		$this->ms->MSPosition = array();
		$this->ms->getListSupply(0, true);
		$MSPosition = $this->ms->MSPosition;
		if(is_array($MSPosition) && count($MSPosition) > 0){
			CProSet::setOption("MS_LAST_UPDATE_SUPPLY", count($MSPosition));
		}
		$this->updateDoc($MSPosition, "SUPPLY");

		sleep(3);

		//запрашиваем и пишем все Возвраты
		$this->ms->MSPosition = array();
		$this->ms->getListSalesReturn(0, true);
		$MSPosition = $this->ms->MSPosition;
		$this->updateDoc($MSPosition, "SALES_RETURN");

		sleep(3);

		//запрашиваем и пишем все Перемещения
		$this->ms->MSPosition = array();
		$this->ms->getListMove(0, true);
		$MSPosition = $this->ms->MSPosition;
		$this->updateDoc($MSPosition, "MOVE");
	}

	public function updateDoc($MSPosition, $type = ""){

		foreach($MSPosition as $arItem){

			$document_id = $arItem["id"];
			$check_id = md5(serialize($arItem));
			//prent($arItem,0,1);die;
			if($this->history[$document_id] && $this->history[$document_id] == $check_id){
				continue;
			}

			$moment = $arItem["moment"];
			$applicable = ($arItem["applicable"] ? "Y" : "N");
			//$sum = $arItem["sum"];

			$agent = $agent_id = $data1 = $data1_id = $data2 = $data2_id = "";

			if ($type == "MOVE") {
				// источник перемещения. со склада
				$url = $arItem["sourceStore"]["meta"]["href"];

				$md5 = md5($url);
				if(!$this->store[$md5]){
					sleep(1);
					$this->logger->log("LOG", "Запрашиваем sourceStore {$url}");
					if($rs = $this->ms->customRequest($url)){

						if($rs["id"] && $rs["name"]){
							$in = array(
								"MS_ID" => "'".addslashes($rs["id"])."'",
								"NAME" => "'".addslashes($rs["name"])."'",
								"UNIQUE_ID" => "'".addslashes($md5)."'",
								"SITE_ID" => "'".addslashes($this->site_id)."'",
							);

							$this->db->Insert("ci_ms_store", $in, $err_mess.__LINE__);

							$this->store[$md5] = $rs["name"];
							$this->storeID[$md5] = $rs["id"];
						}

					} else {
						$this->logger->log("LOG", "Ошибка customRequest sourceStore {$url}");
					}
				}

				if(!$this->store[$md5]){
					$this->logger->log("LOG", "Ошибка загрузки позиции sourceStore" . print_r($arItem, true));
					continue;
				}

				$data1 = $this->store[$md5];
				$data1_id = $this->storeID[$md5];

				// перемещения на какой склад
				$url = $arItem["targetStore"]["meta"]["href"];
				$md5 = md5($url);
				if(!$this->store[$md5]){
					sleep(1);
					if($rs = $this->ms->customRequest($url)){

						if($rs["id"] && $rs["name"]){
							$in = array(
								"MS_ID" => "'".addslashes($rs["id"])."'",
								"NAME" => "'".addslashes($rs["name"])."'",
								"UNIQUE_ID" => "'".addslashes($md5)."'",
								"SITE_ID" => "'".addslashes($this->site_id)."'",
							);

							$this->db->Insert("ci_ms_store", $in, $err_mess.__LINE__);

							$this->store[$md5] = $rs["name"];
							$this->storeID[$md5] = $rs["id"];
						}

					}
				}

				if(!$this->store[$md5]){
					$this->logger->log("LOG", "Ошибка загрузки позиции targetStore" . print_r($arItem, true));
					continue;
				}

				$data2 = $this->store[$md5];
				$data2_id = $this->storeID[$md5];

			} else {
				$url = $arItem["agent"]["meta"]["href"];
				$md5 = md5($url);
				if($url && !$this->agent[$md5]){
					sleep(1);
					if($rs = $this->ms->customRequest($url)){

						if($rs["id"] && $rs["name"]){
							$in = array(
								"MS_ID" => "'".addslashes($rs["id"])."'",
								"NAME" => "'".addslashes($rs["name"])."'",
								"UNIQUE_ID" => "'".addslashes($md5)."'",
								"SITE_ID" => "'".addslashes($this->site_id)."'",
							);

							$this->db->Insert("ci_ms_agent", $in, $err_mess.__LINE__);

							$this->agent[$md5] = $rs["name"];
							$this->agentID[$md5] = $rs["id"];
						}

					}
				}

				if(!$this->agent[$md5]){
					$this->logger->log("LOG", "Ошибка загрузки позиции AGENT" . print_r($arItem, true));
					continue;
				}

				$agent = $this->agent[$md5];
				$agent_id = $this->agentID[$md5];

				/* записываем store в DATA_2 . на какой склад*/
				$url = $arItem["store"]["meta"]["href"];
				$md5 = md5($url);
				if(!$this->store[$md5]){
					sleep(1);
					if($rs = $this->ms->customRequest($url)){

						if($rs["id"] && $rs["name"]){
							$in = array(
								"MS_ID" => "'".addslashes($rs["id"])."'",
								"NAME" => "'".addslashes($rs["name"])."'",
								"UNIQUE_ID" => "'".addslashes($md5)."'",
								"SITE_ID" => "'".addslashes($this->site_id)."'",
							);

							$this->db->Insert("ci_ms_store", $in, $err_mess.__LINE__);

							$this->store[$md5] = $rs["name"];
							$this->storeID[$md5] = $rs["id"];
						}

					}
				}

				if(!$this->store[$md5]){
					$this->logger->log("LOG", "Ошибка загрузки позиции store" . print_r($arItem, true));
					continue;
				}

				$data2 = $this->store[$md5];
				$data2_id = $this->storeID[$md5];

			}




			sleep(1);
			$url = $arItem["positions"]["meta"]["href"];
			$arPosition = $this->ms->customRequest($url);

			foreach($arPosition["rows"] as $position){

				$ar = array();

				$quantity = $position["quantity"];
				$price = $position["price"];

				$url = $position["assortment"]["meta"]["href"];

				$assortment_id = end(explode("/", $url));

				if($assortment_id && !$this->assortment[$assortment_id]){
					sleep(1);
					if($rs = $this->ms->customRequest($url)){
						$name = (strlen($rs["article"]) > 0 ? $rs["article"] : $rs["name"]);
						if($rs["id"] && $name){
							$in = array(
								"MS_ID" => "'".addslashes($rs["id"])."'",
								"XML_ID" => "'".addslashes($rs["externalCode"])."'",
								"NAME" => "'".addslashes($name)."'",
								"SITE_ID" => "'".addslashes($this->site_id)."'",
							);

							$strSql = "SELECT ID, XML_ID FROM b_iblock_element WHERE XML_ID = '{$rs["externalCode"]}' AND IBLOCK_ID = '16'";
							$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
							if ($row = $results->Fetch()){
								$in["BX_ID"] = "'".addslashes($row["ID"])."'";
							}

							$this->db->Insert("ci_ms_assortment", $in, $err_mess.__LINE__);

							$this->assortment[$rs["id"]] = array(
								"XML_ID" => $rs["externalCode"],
								"NAME" => $name,
							);
						}

					}

				}

				if(!$this->assortment[$assortment_id]){
					$this->logger->log("LOG", "Ошибка загрузки позиции 1 AGENT" . print_r($position, true));
					$this->triggers->SetError(["MS. Ошибка загрузки AGENT \r\n"]);
				}

				$ar["DOCUMENT_ID"] = $document_id;

				$ar["AGENT"] = $agent;
				$ar["AGENT_ID"] = $agent_id;

				$ar["DATA_1"] = $data1;
				$ar["DATA_1_ID"] = $data1_id;

				$ar["DATA_2"] = $data2;
				$ar["DATA_2_ID"] = $data2_id;

				$ar["TIMESTAMP"] = $moment;
				$ar["APPLICABLE"] = $applicable;

				$ar["ARTICLE"] = $this->assortment[$assortment_id]["NAME"];
				$ar["PRODUCT_XML_ID"] = $this->assortment[$assortment_id]["XML_ID"];

				$ar["QUANTITY"] = $quantity;
				$ar["PRICE"] = $price;

				$ar["CHECK_ID"] = $check_id;

				$arRes[] = $ar;
				//
				$arDocuments[$document_id] = $document_id;
			}

		}

		$this->setDocuments($arDocuments, $arRes, $type);
	}

	private function setDocuments($arDocuments, $arRes, $type = "SUPPLY"){
		//prent(count($arDocuments), 0,1);die;
		if(count_($arDocuments) == 0) return;

		$arDocuments = array_values($arDocuments);
		if(count_($arDocuments) > 0 && count_($arRes) > 0){
			$this->logger->log("LOG", "Удаляем документы {$type}" . print_r($arDocuments, true));
			$this->db->Query("DELETE FROM ci_ms_history WHERE DOCUMENT_ID IN ('" . implode("','", $arDocuments) . "') AND TYPE = '{$type}'");

			$this->logger->log("LOG", "Добавляем {$this->site_id} - {$type}." . print_r($arRes, true));
			foreach($arRes as $key => $arItem){

				$in = array(
					"DOCUMENT_ID" => "'".addslashes($arItem["DOCUMENT_ID"])."'",
					"AGENT" => "'".addslashes($arItem["AGENT"])."'",
					"AGENT_ID" => "'".addslashes($arItem["AGENT_ID"])."'",
					"DATA_1" => "'".addslashes($arItem["DATA_1"])."'",
					"DATA_1_ID" => "'".addslashes($arItem["DATA_1_ID"])."'",
					"DATA_2" => "'".addslashes($arItem["DATA_2"])."'",
					"DATA_2_ID" => "'".addslashes($arItem["DATA_2_ID"])."'",
					"PRODUCT_XML_ID" => "'".addslashes($arItem["PRODUCT_XML_ID"])."'",
					"ARTICLE" => "'".addslashes($arItem["ARTICLE"])."'",
					"QUANTITY" => $arItem["QUANTITY"],
					"PRICE" => $arItem["PRICE"] / 100,
					"CHECK_ID" => "'".addslashes($arItem["CHECK_ID"])."'",
					"TYPE" => "'".addslashes($type)."'",
					"APPLICABLE" => "'".addslashes($arItem["APPLICABLE"])."'",
					"TIMESTAMP" => "'".addslashes(str_replace(".000", "", $arItem["TIMESTAMP"]))."'",
					"SITE_ID" => "'".addslashes($this->site_id)."'",
				);

				$this->db->Insert("ci_ms_history", $in, $err_mess.__LINE__);

			}
		}
	}

}

(new SyncHistory("s1"))->run();
(new SyncHistory("s2"))->run();
(new SyncHistory("s1_opt"))->run();
(new SyncHistory("msk"))->run();
?>
