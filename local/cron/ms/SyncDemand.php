#!/usr/bin/php
<?php
/*
 * синхронизация отгрузок. вынес отдельно
*/
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);
//if (function_exists('ini_set')) ini_set('memory_limit','1512M');

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class SyncDemand{
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
		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		if (!$this->workers->checkStatus()) {
			$this->logger->log("LOG", "Обработчик занят");
			//exit();
		}

		$this->workers->updateStatus("Y");

		//$this->syncSalesChannel();

		$this->getDemands();

		//$this->sendMS();

		$this->triggers->SendTriggerErrors();
		$this->workers->updateStatus("N");
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
	
	public function getDemands(){
		// получаем отгрузки 
		$limitTimeStamp = 1755019703;
		
		$current_timestamp = time();
		$limitTimeStamp = strtotime('-1 week');

		$arData = $this->ms->getListDemand(false, $limitTimeStamp);
		//$arData = $this->ms->getListDemand(true);
		
		$arDemands = [];
		foreach ($arData as $arItem) {
			if (!$arItem['applicable'] || !$arItem['positions'] || !$arItem['customerOrder']) continue;
			$arDemands[] = [
				'id' => $arItem['id'],
				'name' => $arItem['name'],
				'agent_id' => basename($arItem['agent']['meta']['href']),
				'positions_href' => $arItem['positions']['meta']['href'],
				'salesChannel' => basename($arItem['salesChannel']['meta']['href']),
				'customerOrder' => basename($arItem['customerOrder']['meta']['href']),
				'assortments' => [], // айдишники товаров
				'moment' => $arItem['moment'],
			];
		}

		foreach ($arDemands as $key => &$arItem) {
			$positionMS = $this->ms->customRequest($arItem['positions_href']);
			if (is_array($positionMS) && count($positionMS['rows']) > 0) {
				$assortments = [];
				foreach ($positionMS['rows'] as $v) {
					$assortments[] = basename($v['assortment']['meta']['href']);
				}
				$arItem['assortments'] = $assortments;
			}
			//prent($positionMS);
		}
		unset($arItem);
		
		$arIDs = array_column($arDemands, 'id');
		
		$this->db->Query("DELETE FROM ci_ms_demand_assortments WHERE MS_ID IN ('" . implode("','", $arIDs) . "') AND SITE_ID = '{$this->site_id}'");

		foreach ($arDemands as $key => $arItem) {
			if (!$arItem['assortments']) continue;
			
			$date = DateTime::createFromFormat('Y-m-d H:i:s.u', $arItem["moment"])->format('Y-m-d H:i:s');
			//$data['DATE_DOCUMENT'] = $date->format('Y-m-d H:i:s');
			
			foreach ($arItem['assortments'] as $assortmentID) {
				$in = array(
					"MS_ID" => "'".addslashes($arItem["id"])."'",
					"NAME" => "'".addslashes($arItem["name"])."'",
					"AGENT_ID" => "'".addslashes($arItem["agent_id"])."'",
					"SALES_CHANNEL_ID" => "'".addslashes($arItem["salesChannel"])."'",
					"CUSTOMER_ORDER_ID" => "'".addslashes($arItem["customerOrder"])."'",
					"ASSORTMENT_ID" => "'".addslashes($assortmentID)."'",
					"DATE_DOCUMENT" => "'".addslashes($date)."'",
					"SITE_ID" => "'".addslashes($this->site_id)."'",
				);
//prent($in);  
				$this->db->Insert("ci_ms_demand_assortments", $in, $err_mess.__LINE__);
			}
		}
		
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

(new SyncDemand("s1"))->run();
(new SyncDemand("s2"))->run();
//(new SyncDemand("s1_opt"))->run();
//(new SyncDemand("msk"))->run();
?>
