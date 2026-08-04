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
			"organization" => array(
				"s1" => "27af8b5c-58d1-11ec-0a80-08e7000a6716", // ВОТЧЕС-РИТЕЙЛ
				"s2" => "f595731c-8299-11ec-0a80-0d7800483350",
				"s1_opt" => "adadff58-4d5b-11ed-0a80-0d1b00257948",
				"msk" => "96a1b40f-652b-11ef-0a80-108b0010b66a",
				"msk_minsk" => "96a1b40f-652b-11ef-0a80-108b0010b66a",
			),
			"store" => array(
				"s1" => "79ed7d71-0aa6-11ea-0a80-004200039aa4", // Дубровка
				"s2" => "6f6d2169-180c-11ea-0a80-00b30004eaef",
				"s1_opt" => "adaf74a2-4d5b-11ed-0a80-0d1b0025794a",
				"msk" => "e88d8276-f19c-11ee-0a80-044a000d1702",
				"msk_minsk" => "1c8e4dbf-53e8-11ef-0a80-138c0013657d",
			),
		);

		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		$req = $request->getQueryList()->toArray();

		$this->supplierID = intval($req["supp_id"]);

		$this->transferDoc = false;

		if(in_array($this->supplierID, array(44, 47))){
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

		/*global $USER;
		$arGroups = $USER->GetUserGroupArray();
		if(!$USER->isAdmin() && !in_array(6, $arGroups)){
			global $APPLICATION;
			$APPLICATION->AuthForm("Доступ запрещен");offset"]) >= $result["meta"]["size
			return;
		}*/

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

		$add_where = [];

		$add_where[] = "active = 'Y'";
		$add_where[] = "supp_id IN ('".implode("','", $this->arSuppID)."')";
		if($this->supplierID == 47){
			$add_where[] = "site_id IN ('s2','s3')";
		}elseif($this->supplierID == 44){
			// $add_where[] = "site_id = 's1'";
			$add_where[] = "site_id IN ('s1','s1_nkz')";
		}

		$strSql = "SELECT * FROM ci_purchase WHERE ".implode(" AND ", $add_where)."";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$ms_data = unserialize($row["ms_data"]);

			if(($this->transferDoc === true && !$ms_data["supply_transfer"]["id"]) || ($this->transferDoc === false && !$ms_data["supply"]["id"])){
				$this->purchaseList[] = $row;
				$this->arProductID[$row["product_id"]] = false;
			}
		}

		//prent($this->purchaseList);die;
	}

	public function getProduct(){
		if(!$this->arProductID) return;

		$strSql = "SELECT SITE_ID,MS_ID,BX_ID FROM ci_ms_assortment WHERE BX_ID IN ('".implode("','", array_keys($this->arProductID))."')";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($this->arProductID[$row["BX_ID"]]))
				$this->arProductID[$row["BX_ID"]][$row["SITE_ID"]] = $row["MS_ID"];
		}
	}
	// отправляем в мойсклад
	public function sendMS(){
		if(!$this->purchaseList) return false;

		$this->logger->log("LOG", "Создаем для {$this->supplierID}", $this->purchaseList);
		$arSupply = [];
		$supp = $this->supplierList[$this->supplierID];

		$this->rate = 1;
		$ms_cabinet = $supp["ms_cabinet"];

		/*
		1. Склад Москва - приемка в кабинете (tempusby) + возврат в кабинете (tempusint). (фильтр товаров - "S2 + Валюта списка RUB")
		2. Склад Минск - приемка в кабинете (tempusint) + возврат в кабинете (tempusby). (фильтр товаров - "S1 + Валюта списка BYN")
		3. Склад Москва ОПТ - приемка в кабинете (tempusint) + отгрузка в кабинете (tempusws). (фильтр товаров - "только свой блок с товарами")
		4. остальные - приемка в кабинете в зависимости от "Валюта списка". (фильтр товаров - "только свой блок с товарами")
		*/

		$msAgentID = $supp["ms_id"];

		if($this->supplierID == 47){
			// Склад Москва

			$currency = $this->currency->getDetail("BYN");//курс валюты
			$this->rate = $currency["rate"];

			// создаем приёмки
			$this->createDocument("s2", "supply", $msAgentID);

			// создаем возвраты
			$this->rate = 1;
			$msAgentID = "1de0fc7d-3c75-11ea-0a80-0652000ce149";
			$this->createDocument("s1", "purchasereturn", $msAgentID);

		}elseif($this->supplierID == 44){
			// Склад Минск
			// создаем приёмки
			// $this->chronos44BY = '';
			// $this->agent44BY = '';
			$msAgentID = "1de0fc7d-3c75-11ea-0a80-0652000ce149";
			$this->createDocument("s1", "supply", $msAgentID);

			// $msAgentID = "ccf468ad-1073-11ee-0a80-10c50002f920";
			// $this->createDocument("msk", "supply", $msAgentID,'msk_minsk');

			// $msAgentID = "ccaa773a-1073-11ee-0a80-10c50002f8eb";
			// $this->createDocument("msk", "demand", $msAgentID, 'msk_minsk');

			// создаем возвраты
			$currency = $this->currency->getDetail("BYN");//курс валюты
			$this->rate = $currency["rate"];
			$msAgentID = "d9f84aa8-9a7e-11ef-0a80-0fc20065d55e";
			$this->createDocument("s2", "purchasereturn", $msAgentID);

		}elseif($this->supplierID == 103){
			// Склад Москва ОПТ

			$msAgentID = "01c31e04-4d69-11ed-0a80-0238002481b5";
			// создаем приёмки
			$this->createDocument("s1", "supply", $msAgentID);

			// создаем отгрузки
			//$msAgentID = "fc87c337-4d88-11ed-0a80-072b0002054b";
			//$this->createDocument("s1_opt", "demand", $msAgentID);
		}elseif($this->supplierID == 116){
			// Склад Москва ОПТ

			$msAgentID = "01c31e04-4d69-11ed-0a80-0238002481b5";
			// создаем приёмки
			$this->createDocument("s1", "supply", $msAgentID);

		// }elseif($this->supplierID == 124){
		// 	// Склад Москва ОПТ
		//
		// 	$msAgentID = "bd95597d-dfde-11ee-0a80-0bd50002c157";
		// 	// создаем приёмки
		// 	$this->createDocument("msk", "supply", $msAgentID);
		//
		}elseif($this->supplierID == 129){
			// Склад Москва 2
			// создаем приёмки
			$this->FakeCreateDocument("s1", "supply", $msAgentID);

		}else{
			if($supp["currency"] != "RUB"){
				$currency = $this->currency->getDetail($supp["currency"]);//курс валюты
				$this->rate = $currency["rate"];
			}

			// создаем приёмки
			$this->createDocument($supp["ms_cabinet"], "supply", $msAgentID);
		}

		/*
		$this->ms = new MoyskladAPI($ms_cabinet);

		if($this->supplierID == 47 || $this->supplierID == 44){
				$this->logger->log("LOG", "Создаем возврат для {$this->supplierID}");

				if(!$supply["id"]){
					$this->logger->log("LOG", "Отмена. Приемка не создана");
					return false;
				}

				if($this->supplierID == 47){
					$ms_cabinet = "s1";
					$supp["ms_id"] = "1de0fc7d-3c75-11ea-0a80-0652000ce149";
					$this->rate = 1;
				}else{
					$ms_cabinet = "s2";
					$supp["ms_id"] = "236a0c9f-35dd-11ea-0a80-0217000fedca";
					$currency = $this->currency->getDetail("BYN");//курс валюты
					$this->rate = $currency["rate"];
				}

				$this->ms = new MoyskladAPI($ms_cabinet);
				$template = $this->ms->send("/entity/purchasereturn/new", "PUT", [], ["Content-Type" => "application/json"]);

				if(!$template){
					$this->logger->log("LOG", "Отмена. Шаблон возврата не получен", $template);
					return false;
				}

				$arPosition = $this->preparePositions($ms_cabinet);

				$template["positions"] = $arPosition["items"];
				$posID = $arPosition["posID"];

				$this->logger->log("LOG", "positions purchasereturn", $template["positions"]);

				$this->prepareTemplate($template, $ms_cabinet, $supp["ms_id"]);

				$template["description"] = "Возврат поставщику созданный через API";
				$createReturn = $this->ms->send("/entity/purchasereturn", "POST", $template, ["Content-Type" => "application/json"]);

				$this->logger->log("LOG", "Ответ от MS purchasereturn", $createReturn);

				if($createReturn["id"] && count($posID) > 0){
					$this->logger->log("LOG", "posID createReturn", $posID);
					$strSql = "SELECT * FROM ci_purchase WHERE id IN ('".implode("','", $posID)."')";
					$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
					if ($row = $results->Fetch()){
						$up = unserialize($row["ms_data"]);
						$up["purchasereturn"] = array(
							"id" => $createReturn["id"],
							"name" => $createReturn["name"],
							"created" => $createReturn["created"],
							"cabinet" => $ms_cabinet,
							"timestamp" => microtime(true),
						);
						$this->logger->log("LOG", "Обновляем данные у возвратов ", $up);

						$this->db->Update("ci_purchase", array("ms_data" => "'".addslashes(serialize($up))."'"), "WHERE id IN ('".implode("','", $posID)."')", $err_mess.__LINE__);

					}




					//prent($up);
				}
		}*/

	}
	public function FakecreateDocument($ms_cabinet = "s1", $type = "supply", $msAgentID = ""){
		//return false;
		$template = $this->getTemplate($ms_cabinet, $type);

		if(!$template){
			$this->logger->log("LOG", "Отмена. Шаблон приёмки не получен");
			return false;
		}

		$arPosition = $this->preparePositions($ms_cabinet);

		$template["positions"] = $arPosition["items"];
		$posID = $arPosition["posID"];

		//$this->logger->log("LOG", "{$type} positions ", $template["positions"]);

		$this->prepareTemplate($template, $ms_cabinet, $msAgentID);

		// создаем проведенными в складах
		$template["applicable"] = true;

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
					"id" => rand(0,13000) + time(),
					"name" => 'fake',
					"created" => 'random',
					"cabinet" => $ms_cabinet,
					"timestamp" => $microtime,
				);

				//$this->logger->log("LOG", "Обновляем данные у возвратов ", $arMS);

				$this->db->Update("ci_purchase", array("ms_data" => "'".addslashes(serialize($arMS))."'"), "WHERE id = '{$purchase_id}'", $err_mess.__LINE__);

			}
		}

	}

	public function createDocument($ms_cabinet = "s1", $type = "supply", $msAgentID = "",$fakeCabinet = false){
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

		$this->prepareTemplate($template, $ms_cabinet, $msAgentID, $fakeCabinet);

		// создаем проведенными в складах
		if(in_array($this->supplierID, array(44, 47))){
			$template["applicable"] = true;
		}

		if(in_array($this->supplierID, array(103, 116))){
			$template["applicable"] = false;
		}

		$doc = $this->ms->send("/entity/{$type}", "POST", $template, ["Content-Type" => "application/json"]);
		if ( $type == 'supply' ){
			file_put_contents(
				'/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/purchase/supplyCheck_'.$this->supplierID.'.txt',
				print_r($doc, true)
			);
			file_put_contents(
				'/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/purchase/supplyTemplateCheck_'.$this->supplierID.'.txt',
				print_r($template, true)
			);
		}
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
			//$up = [];


			//$this->logger->log("LOG", "{$type} posID", $posID);
			//$this->logger->log("LOG", "{$type} Обновляем данные", $up);

			//$this->db->Update("ci_purchase", array("ms_data" => "'".addslashes(serialize($up))."'"), "WHERE id IN ('".implode("','", $posID)."')", $err_mess.__LINE__);
		}
	}

	// получаем шаблон от MS
	public function getTemplate($ms_cabinet = "s1", $type = "supply"){
		$this->ms = new MoyskladAPI($ms_cabinet);
		$template = $this->ms->send("/entity/{$type}/new", "PUT", [], ["Content-Type" => "application/json"]);
		return $template;
	}

	public function preparePositions($ms_cabinet = "s1"){

		$quantity = [];
		foreach($this->purchaseList as $k => $arItem){

			if($this->arProductID[$arItem["product_id"]][$ms_cabinet]){
				$quantity[$arItem["product_id"]] += 1;
				$price = round($arItem["price"] / $this->rate, 2);

				$arPosition[$arItem["product_id"]] = [
					"quantity" => (float)$quantity[$arItem["product_id"]],
					"price" => (float)$price * 100,
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

	public function prepareTemplate(&$template, $ms_cabinet = "s1", $ms_id = "", $fake_cabinet = false){
		if ($fake_cabinet) {
			$ms_cabinet = $fake_cabinet;
		}

		$template["store"] = array(
			"meta" => array(
				"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/" . $this->arMS["store"][$ms_cabinet],
				"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/store/metadata",
				"type" => "store",
				"mediaType" => "application/json"
			)
		);

		$template["organization"] = array(
			"meta" => array(
				"href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/" . $this->arMS["organization"][$ms_cabinet],
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
