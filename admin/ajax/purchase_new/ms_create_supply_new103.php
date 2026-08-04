#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");?>
<?
global $DB;
?>
<?

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class PurchaseMS{

	private $supplierList;
	private $purchaseList;
	private $arSuppID;

    function __construct(){

        $this->logger = new TsLogger("/ajax/purcahse/".__CLASS__."/");

		$this->connection = Application::getConnection();

        global $DB;
        $this->db = $DB;

		$this->loadModules();

    $this->supplier = new CPanelSupplier;
		$this->currency = new CPanelCurrency;

		$this->arMS = array(
			"chronos" => array(
				"contragent" => array(
					"ip" => "0f31eb40-7b0e-11ef-0a80-0cd400179974",
					"watchs-retail" => "ccaa773a-1073-11ee-0a80-10c50002f8eb",
					"watch-treid" => "a61907cd-f1c5-11ee-0a80-0102001642d5",
					"time-line" => "0f31eb40-7b0e-11ef-0a80-0cd400179974",
				),
				"organization" => array(
					//"chronos" => "83be7e8b-0f74-11ee-0a80-143a0014a100",
					"chronos" => "96a1b40f-652b-11ef-0a80-108b0010b66a",
				),
			),
			"int" => array(
				"contragent" => array(
						//"chronos" => "01c31e04-4d69-11ed-0a80-0238002481b5",
						"chronos" => "a389e34e-6901-11ef-0a80-0c11003c56a7",
					),
					"organization" => array(
						"ip" => "655c12e4-ae44-11ee-0a80-08ce006d3fdc",
						"watchs-retail" => "27af8b5c-58d1-11ec-0a80-08e7000a6716",
						"time-line" => "655c12e4-ae44-11ee-0a80-08ce006d3fdc",
					),
					"store" => "79ed7d71-0aa6-11ea-0a80-004200039aa4",
			  ),
				"by" => array(
					"contragent" => array(
						"chronos" => "10bf93f1-4d5c-11ed-0a80-01aa0024eec5",
					),
					"organization" => array(
						"watch-treid" => "6812f6e0-aa06-11ee-0a80-138b002c126e",
					),
					"store" => "6f6d2169-180c-11ea-0a80-00b30004eaef",
			  ),
		);

		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		$req = $request->getQueryList()->toArray();

		$this->supplierID = intval($req["supp_id"]);

		$this->transferDoc = false;

		if(in_array($this->supplierID, array(103))){
			$this->transferDoc = true;

		}
  }

	private function loadModules()
    {
		Loader::includeModule("panel.manager");
		Loader::includeModule("iblock");
        Loader::includeModule("catalog");
    }

    public function run(){


		$this->logger->log("LOG", "Запуск обработчика");

		if(!$this->supplierID){
			$this->logger->log("LOG", "Не заполнен supplierID");
			return false;
		}

        $this->getSupplier();
        $this->getPurchase();

		// ищем ID товаров в мойсклад
        $this->getProduct();

        $this->sendMS();

		$this->logger->log("LOG", "Конец обработчика");
    }

	public function getSupplier(){
		$arFilter = [];

		if($this->supplierID){
			//$arFilter = array("id" => $this->supplierID);
		}

		$arList = $this->supplier->getList($arFilter);
		foreach($arList as $arItem){
			$settings = json_decode($arItem["settings"], true);
			if(strlen($settings["mc_name"]) > 0 && in_array($settings["currency_list"], ["RUB", "BYN"])){
				$this->supplierList[$arItem["id"]] = [
					"id" => $arItem["id"],
					"ms_id" => $settings["mc_name"],
					"ms_cabinet" => ($settings["currency_list"] == "RUB" ? "s1" : "s2"),
					"currency" => $settings["currency_list"],
				];

				if($this->supplierID == 47 && $settings["currency_list"] == "RUB"){
					$this->arSuppID[] = $arItem["id"];
				}elseif($this->supplierID == 44 && $settings["currency_list"] == "BYN"){
					$this->arSuppID[] = $arItem["id"];
				}elseif($this->supplierID == $arItem["id"]){
					$this->arSuppID[] = $arItem["id"];
				}
			}

		}
	}

	public function getPurchase(){
		if(!$this->arSuppID) return;
		\Bitrix\Main\Loader::includeModule('sale');
		$add_where = [];

		$add_where[] = "active = 'Y'";
		$add_where[] = "supp_id IN ('".implode("','", $this->arSuppID)."')";

		$strSql = "SELECT * FROM ci_purchase WHERE ".implode(" AND ", $add_where)."";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$ms_data = unserialize($row["ms_data"]);

			if(($this->transferDoc === true && !$ms_data["supply_transfer"]["id"]) || ($this->transferDoc === false && !$ms_data["supply"]["id"])){
				if ($row['order_id'] != 0 && !empty($row['order_id'])) {
					$orderId = $row['order_id'];
					$order = \Bitrix\Sale\Order::load($orderId);



						if ($order) {
						    $propertyCollection = $order->getPropertyCollection();
						    foreach ($propertyCollection as $property) {
										if ($property->getField('ORDER_PROPS_ID') == '57') {
											if (!empty($property->getValue()) and $property->getValue() != '') {
												$row['source'] = 'wb';
											} else {
												if($order->getField('USER_ID') == '172147') {
													$row['source'] = 'ozon_ti';
												} else if ($order->getField('USER_ID') == '122907'){
													//$row['source'] = 'ozon';
													$row['source'] = 'other';
												} else {
													$row['source'] = 'other';
												}
											}
										}
						    }
						}
				} else {
					$row['source'] = 'other';
				}


				$this->purchaseList[] = $row;
				$this->arProductID[$row["product_id"]] = false;
			}
		}
		//print_r($this->purchaseList);
		// die();
	}

	public function getProduct(){
		if(!$this->arProductID) return;
		$this->ms = new MoyskladAPI('msk');
		$this->stockArray = [];
		$strSql = "SELECT SITE_ID,MS_ID,BX_ID FROM ci_ms_assortment WHERE BX_ID IN ('".implode("','", array_keys($this->arProductID))."')";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($this->arProductID[$row["BX_ID"]])) {
				$this->arProductID[$row["BX_ID"]][$row["SITE_ID"]] = $row["MS_ID"];
            }
            if ($row["SITE_ID"] == 'msk') {
				$res = $this->ms->send("/report/stock/bystore/current?filter=assortmentId=".$row["MS_ID"]."", "GET", [], ["Content-Type" => "application/json"]);
				foreach ($res as $value) {
                    if(
                        $value['storeId'] == '83c00532-0f74-11ee-0a80-143a0014a102' ||
                        $value['storeId'] == '796d5aa2-bab0-11ee-0a80-03440010c9e0' ||
                        $value['storeId'] == 'e7c0d649-55ef-11ee-0a80-1186002ba09f'
                    ) {
						$this->stockArray[$row["BX_ID"]] = $value['storeId'];
                    }
                }
			}
		}
		// $strSql = "SELECT product_id,code,site_id FROM ci_ms_directory_products WHERE site_id = 'msk' AND code IN ('".implode("','", array_keys($this->arProductID))."')";
		// $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		// while ($row = $results->Fetch()){
		// 	if(isset($this->arProductID[$row["code"]]))
		// 		$this->arProductID[$row["code"]][$row["site_id"]] = $row["product_id"];
		// }



		/*foreach ($this->arProductID as $pr_id => $site_id) {
			$this->ms = new MoyskladAPI('msk');
			$res = $this->ms->send("/report/stock/bystore/current?filter=assortmentId=".$site_id['msk']."", "GET", [], ["Content-Type" => "application/json"]);
			foreach ($res as $value) {
				if($value['storeId'] == '83c00532-0f74-11ee-0a80-143a0014a102' || $value['storeId'] == '796d5aa2-bab0-11ee-0a80-03440010c9e0' || $value['storeId'] == 'e7c0d649-55ef-11ee-0a80-1186002ba09f')
				$this->stockArray[$site_id['msk']] = $value['storeId'];
			}
		}*/
	}
	// отправляем в мойсклад
	public function sendMS(){

		if(!$this->purchaseList) return false;

		$this->logger->log("LOG", "Создаем для {$this->supplierID}", $this->purchaseList);
		$arSupply = [];
		$supp = $this->supplierList[$this->supplierID];
		$tmpProductsArr = [];

		foreach ($this->purchaseList as $key => $value) {
			if (isset($this->stockArray[$value['product_id']])) {
				$sklad = $this->stockArray[$value['product_id']];
			} else {
				$sklad = '83c00532-0f74-11ee-0a80-143a0014a102';
			}
			if ($value['site_id'] == 's1') {
				if ($value['source'] == 'wb'){
					$tmpProductsArr[$sklad]['s1']['wb'][] = $value;
				} else if ($value['source'] == 'ozon_ti') {
					$tmpProductsArr[$sklad]['s1']['ozon_ti'][] = $value;
				}else {
					$tmpProductsArr[$sklad]['s1']['other'][] = $value;
				}
			} else if ($value['site_id'] == 's2') {
					$tmpProductsArr[$sklad]['s2']['other'][] = $value;
			}
		}

		$this->rate = 1;

		foreach ($tmpProductsArr as $sklad => $valueArray) {
			//wb
			if (isset($valueArray['s1']['wb']) && count($valueArray['s1']['wb']) > 0){
				$this->ms = new MoyskladAPI('s1');
				print_r('wb_s1');
				$templateSupplyS1 = $this->ms->send("/entity/supply/new", "PUT", [], ["Content-Type" => "application/json"]);
				$arPosition = $this->preparePositions("s1",$valueArray['s1']['wb']);

				$templateSupplyS1["positions"] = $arPosition["items"];
				$posID = $arPosition["posID"];

				$this->logger->log("LOG", "{$type} positions ", $template["positions"]);

				$templateSupplyS1["store"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $this->arMS["int"]['store'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/store/metadata",
						"type" => "store",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["organization"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/" . $this->arMS["int"]['organization']['watchs-retail'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/metadata",
						"type" => "organization",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["agent"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/" . $this->arMS["int"]['contragent']['chronos'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata",
						"type" => "counterparty",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["applicable"] = false;

				$doc = $this->ms->send("/entity/supply", "POST", $templateSupplyS1, ["Content-Type" => "application/json"]);
				if($doc["id"] && count($posID) > 0){
					$type = "supply_transfer";
					$microtime = microtime(true);
					foreach($posID as $purchase_id){
						$strSql = "SELECT * FROM ci_purchase WHERE id = '{$purchase_id}'";
						$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
						if ($row = $results->Fetch()){

							if($row["ms_data"]){
								$arMS = unserialize($row["ms_data"]);
							}else{
								$arMS = [];
							}

							$arMS[$type] = array(
								"id" => $doc["id"],
								"name" => $doc["name"],
								"created" => $doc["created"],
								"cabinet" => 's1',
								"timestamp" => $microtime,
							);

							$this->logger->log("LOG", "Обновляем данные у возвратов ", $arMS);

							$this->db->Update("ci_purchase", array("ms_data" => "'".addslashes(serialize($arMS))."'"), "WHERE id = '{$purchase_id}'", $err_mess.__LINE__);

						}
					}
				}
				if ($doc) {
					$this->ms = new MoyskladAPI('msk');


					//$templateDemand["name"] = 'testfromapi';
					$templateDemand["store"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $sklad,
							"type" => "store",
							"mediaType" => "application/json"
						)
					);
					$templateDemand["organization"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/" . $this->arMS["chronos"]['organization']['chronos'],
							"type" => "organization",
							"mediaType" => "application/json"
						)
					);
					$templateDemand["state"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/8624d958-0f74-11ee-0a80-143a0014a1a0",
							"type" => "state",
							"mediaType" => "application/json"
						)
					);
					$templateDemand["agent"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/" . $this->arMS["chronos"]['contragent']['watchs-retail'],
							"type" => "counterparty",
							"mediaType" => "application/json"
						)
					);

					$templateDemand["applicable"] = true;
					print_r('wb_msk');
					$arPosition = $this->preparePositions("msk",$valueArray['s1']['wb']);
					$templateDemand["positions"] = $arPosition["items"];
					//print_r($arPosition["items"]);
					// die();
					$doc2 = $this->ms->send("/entity/customerorder", "POST", $templateDemand, ["Content-Type" => "application/json"]);

				}
			}
			//ozon
			if (isset($valueArray['s1']['ozon_ti']) && count($valueArray['s1']['ozon_ti']) > 0){
				$this->ms = new MoyskladAPI('s1');
				print_r('ozon_ti');
				$templateSupplyS1 = $this->ms->send("/entity/supply/new", "PUT", [], ["Content-Type" => "application/json"]);
				$arPosition = $this->preparePositions("s1",$valueArray['s1']['ozon_ti']);

				$templateSupplyS1["positions"] = $arPosition["items"];
				$posID = $arPosition["posID"];

				$this->logger->log("LOG", "{$type} positions ", $template["positions"]);

				$templateSupplyS1["store"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $this->arMS["int"]['store'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/store/metadata",
						"type" => "store",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["organization"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/" . $this->arMS["int"]['organization']['time-line'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/metadata",
						"type" => "organization",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["agent"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/" . $this->arMS["int"]['contragent']['chronos'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata",
						"type" => "counterparty",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["applicable"] = false;

				$doc = $this->ms->send("/entity/supply", "POST", $templateSupplyS1, ["Content-Type" => "application/json"]);
				if($doc["id"] && count($posID) > 0){
					$type = "supply_transfer";
					$microtime = microtime(true);
					foreach($posID as $purchase_id){
						$strSql = "SELECT * FROM ci_purchase WHERE id = '{$purchase_id}'";
						$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
						if ($row = $results->Fetch()){

							if($row["ms_data"]){
								$arMS = unserialize($row["ms_data"]);
							}else{
								$arMS = [];
							}

							$arMS[$type] = array(
								"id" => $doc["id"],
								"name" => $doc["name"],
								"created" => $doc["created"],
								"cabinet" => 's1',
								"timestamp" => $microtime,
							);

							$this->logger->log("LOG", "Обновляем данные у возвратов ", $arMS);

							$this->db->Update("ci_purchase", array("ms_data" => "'".addslashes(serialize($arMS))."'"), "WHERE id = '{$purchase_id}'", $err_mess.__LINE__);

						}
					}
				}
				if ($doc) {
					$this->ms = new MoyskladAPI('msk');


					//$templateDemand["name"] = 'testfromapi';
					$templateDemand["store"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $sklad,
							"type" => "store",
							"mediaType" => "application/json"
						)
					);
					$templateDemand["organization"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/" . $this->arMS["chronos"]['organization']['chronos'],
							"type" => "organization",
							"mediaType" => "application/json"
						)
					);
					$templateDemand["state"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/8624d958-0f74-11ee-0a80-143a0014a1a0",
							"type" => "state",
							"mediaType" => "application/json"
						)
					);
					$templateDemand["agent"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/" . $this->arMS["chronos"]['contragent']['time-line'],
							"type" => "counterparty",
							"mediaType" => "application/json"
						)
					);

					$templateDemand["applicable"] = true;
					print_r('ozon_ti_msk');
					$arPosition = $this->preparePositions("msk",$valueArray['s1']['ozon_ti']);
					$templateDemand["positions"] = $arPosition["items"];
					//print_r($arPosition["items"]);
					// die();
					$doc2 = $this->ms->send("/entity/customerorder", "POST", $templateDemand, ["Content-Type" => "application/json"]);

				}
			}
			//other
			if (isset($valueArray['s1']['other']) && count($valueArray['s1']['other']) > 0){
				$this->ms = new MoyskladAPI('s1');
				$templateSupplyS1 = $this->ms->send("/entity/supply/new", "PUT", [], ["Content-Type" => "application/json"]);
				print_r('ot_s1');
				$arPosition = $this->preparePositions("s1",$valueArray['s1']['other']);

				$templateSupplyS1["positions"] = $arPosition["items"];
				$posID = $arPosition["posID"];

				$this->logger->log("LOG", "{$type} positions ", $template["positions"]);

				$templateSupplyS1["store"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $this->arMS["int"]['store'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/store/metadata",
						"type" => "store",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["organization"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/" . $this->arMS["int"]['organization']['ip'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/metadata",
						"type" => "organization",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["agent"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/" . $this->arMS["int"]['contragent']['chronos'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata",
						"type" => "counterparty",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["applicable"] = false;

				$doc = $this->ms->send("/entity/supply", "POST", $templateSupplyS1, ["Content-Type" => "application/json"]);
				if($doc["id"] && count($posID) > 0){
					$type = "supply_transfer";
					$microtime = microtime(true);
					foreach($posID as $purchase_id){
						$strSql = "SELECT * FROM ci_purchase WHERE id = '{$purchase_id}'";
						$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
						if ($row = $results->Fetch()){

							if($row["ms_data"]){
								$arMS = unserialize($row["ms_data"]);
							}else{
								$arMS = [];
							}

							$arMS[$type] = array(
								"id" => $doc["id"],
								"name" => $doc["name"],
								"created" => $doc["created"],
								"cabinet" => 's1',
								"timestamp" => $microtime,
							);

							$this->logger->log("LOG", "Обновляем данные у возвратов ", $arMS);

							$this->db->Update("ci_purchase", array("ms_data" => "'".addslashes(serialize($arMS))."'"), "WHERE id = '{$purchase_id}'", $err_mess.__LINE__);

						}
					}
				}
				if ($doc) {
					$this->ms = new MoyskladAPI('msk');

					$templateDemand["store"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $sklad,
							"type" => "store",
							"mediaType" => "application/json"
						)
					);
					//$templateDemand["name"] = 'testfromapi';
					$templateDemand["organization"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/" . $this->arMS["chronos"]['organization']['chronos'],
							"type" => "organization",
							"mediaType" => "application/json"
						)
					);
					$templateDemand["state"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/8624d958-0f74-11ee-0a80-143a0014a1a0",
							"type" => "state",
							"mediaType" => "application/json"
						)
					);
					$templateDemand["agent"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/" . $this->arMS["chronos"]['contragent']['ip'],
							"type" => "counterparty",
							"mediaType" => "application/json"
						)
					);

					$templateDemand["applicable"] = true;
					print_r('ot_msk');
					$arPosition = $this->preparePositions("msk",$valueArray['s1']['other']);
					$templateDemand["positions"] = $arPosition["items"];
					//print_r($arPosition["items"]);
					// die();
					$doc2 = $this->ms->send("/entity/customerorder", "POST", $templateDemand, ["Content-Type" => "application/json"]);

				}
			}
			//s2
			if (isset($valueArray['s2']['other']) && count($valueArray['s2']['other']) > 0){
				$this->ms = new MoyskladAPI('s2');
				$templateSupplyS1 = $this->ms->send("/entity/supply/new", "PUT", [], ["Content-Type" => "application/json"]);
				print_r('ot_s2');
				$arPosition = $this->preparePositions("s2",$valueArray['s2']['other']);

				$templateSupplyS1["positions"] = $arPosition["items"];
				$posID = $arPosition["posID"];


				$templateSupplyS1["store"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $this->arMS["by"]['store'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/store/metadata",
						"type" => "store",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["organization"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/" . $this->arMS["by"]['organization']['watch-treid'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/metadata",
						"type" => "organization",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["agent"] = array(
					"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/" . $this->arMS["by"]['contragent']['chronos'],
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata",
						"type" => "counterparty",
						"mediaType" => "application/json"
					)
				);

				$templateSupplyS1["applicable"] = false;

				$doc = $this->ms->send("/entity/supply", "POST", $templateSupplyS1, ["Content-Type" => "application/json"]);
				if($doc["id"] && count($posID) > 0){
					$type = "supply_transfer";
					$microtime = microtime(true);
					foreach($posID as $purchase_id){
						$strSql = "SELECT * FROM ci_purchase WHERE id = '{$purchase_id}'";
						$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
						if ($row = $results->Fetch()){

							if($row["ms_data"]){
								$arMS = unserialize($row["ms_data"]);
							}else{
								$arMS = [];
							}

							$arMS[$type] = array(
								"id" => $doc["id"],
								"name" => $doc["name"],
								"created" => $doc["created"],
								"cabinet" => 's2',
								"timestamp" => $microtime,
							);

							$this->logger->log("LOG", "Обновляем данные у возвратов ", $arMS);

							$this->db->Update("ci_purchase", array("ms_data" => "'".addslashes(serialize($arMS))."'"), "WHERE id = '{$purchase_id}'", $err_mess.__LINE__);

						}
					}
				}
				if ($doc) {
					$this->ms = new MoyskladAPI('msk');

					$templateDemand["store"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $sklad,
							"type" => "store",
							"mediaType" => "application/json"
						)
					);
					//$templateDemand["name"] = 'testfromapi';
					$templateDemand["organization"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/" . $this->arMS["chronos"]['organization']['chronos'],
							"type" => "organization",
							"mediaType" => "application/json"
						)
					);
					$templateDemand["state"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/8624d958-0f74-11ee-0a80-143a0014a1a0",
							"type" => "state",
							"mediaType" => "application/json"
						)
					);

					$templateDemand["agent"] = array(
						"meta" => array(
							"href" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/" . $this->arMS["chronos"]['contragent']['watch-treid'],
							"type" => "counterparty",
							"mediaType" => "application/json"
						)
					);

					$templateDemand["applicable"] = true;
					print_r('ots2_msk');
					$arPosition = $this->preparePositions("msk",$valueArray['s2']['other']);
					$templateDemand["positions"] = $arPosition["items"];
					//print_r($arPosition["items"]);
					// die();
					$doc2 = $this->ms->send("/entity/customerorder", "POST", $templateDemand, ["Content-Type" => "application/json"]);

				}
			}

		}
	}


		public function createDocument($ms_cabinet = "s1", $type = "supply", $msAgentID = ""){
			//return false;
			$template = $this->getTemplate($ms_cabinet, $type);

			if(!$template){
				$this->logger->log("LOG", "Отмена. Шаблон приёмки не получен");
				return false;
			}

			$arPosition = $this->preparePositions($ms_cabinet);

			$template["positions"] = $arPosition["items"];
			$posID = $arPosition["posID"];

			$this->logger->log("LOG", "{$type} positions ", $template["positions"]);

			$this->prepareTemplate($template, $ms_cabinet, $msAgentID);

			// создаем проведенными в складах
			if(in_array($this->supplierID, array(44, 47))){
				$template["applicable"] = true;
			}

			if(in_array($this->supplierID, array(103, 116))){
				$template["applicable"] = false;
			}

		$doc = $this->ms->send("/entity/{$type}", "POST", $template, ["Content-Type" => "application/json"]);

		$this->logger->log("LOG", "Ответ от MS {$type}", $doc);

		if($doc["id"] && count($posID) > 0){
			if($this->transferDoc === true && $type == "supply") $type = "supply_transfer";
			$microtime = microtime(true);
			foreach($posID as $purchase_id){
				$strSql = "SELECT * FROM ci_purchase WHERE id = '{$purchase_id}'";
				$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){

					if($row["ms_data"]){
						$arMS = unserialize($row["ms_data"]);
					}else{
						$arMS = [];
					}

					$arMS[$type] = array(
						"id" => $doc["id"],
						"name" => $doc["name"],
						"created" => $doc["created"],
						"cabinet" => $ms_cabinet,
						"timestamp" => $microtime,
					);

					$this->logger->log("LOG", "Обновляем данные у возвратов ", $arMS);

					$this->db->Update("ci_purchase", array("ms_data" => "'".addslashes(serialize($arMS))."'"), "WHERE id = '{$purchase_id}'", $err_mess.__LINE__);

				}
			}
		}
	}

	// получаем шаблон от MS
	public function getTemplate($ms_cabinet = "s1", $type = "supply"){
		$this->ms = new MoyskladAPI($ms_cabinet);
		$template = $this->ms->send("/entity/{$type}/new", "PUT", [], ["Content-Type" => "application/json"]);
		return $template;
	}

	public function preparePositions($ms_cabinet = "s1",$items = array()){

		$quantity = [];
		foreach($items as $k => $arItem){

			if($this->arProductID[$arItem["product_id"]][$ms_cabinet]){
				$quantity[$arItem["product_id"]] += 1;
				$price = round($arItem["price"] / $this->rate, 2);

				$arPosition[$arItem["product_id"]] = [
					"quantity" => (float)$quantity[$arItem["product_id"]],
					"reserve" => (float)$quantity[$arItem["product_id"]],
					"price" => (float)$price * 100,
					"vat" => 20,
 					"assortment" => array(
						"meta" => array(
						"href" => "https://api.moysklad.ru/api/remap/1.2/entity/product/{$this->arProductID[$arItem["product_id"]][$ms_cabinet]}",
						"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/product/metadata",
						"type" => "product",
						"mediaType" => "application/json"
						)
					)
				];

				$posID[] = $arItem["id"];
			}

		}
		$arPosition = array_values($arPosition);

		return ["items" => $arPosition, "posID" => $posID];
	}

	public function prepareTemplateS1(&$template, $ms_cabinet = "s1", $ms_id = ""){

		$template["store"] = array(
			"meta" => array(
				"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $this->arMS["int"]['store'],
				"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/store/metadata",
				"type" => "store",
				"mediaType" => "application/json"
			)
		);

		$template["organization"] = array(
			"meta" => array(
				"href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/" . $this->arMS["int"][$ms_cabinet],
				"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/metadata",
				"type" => "organization",
				"mediaType" => "application/json"
			)
		);

		$template["agent"] = array(
			"meta" => array(
				"href" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/" . $ms_id,
				"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata",
				"type" => "counterparty",
				"mediaType" => "application/json"
			)
		);

		$template["applicable"] = false;
	}

}
(new PurchaseMS())->run();
?>
