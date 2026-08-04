#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

require($_SERVER['DOCUMENT_ROOT'] . '/local/classes/WildberriesAPI.php');

set_time_limit(3600);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class UpdateOrderFBO{
	private $orders = [];
	private $ordersBX = [];
	private const HL_BLOCK_ID = 8;
	
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;
		$this->wb = new WildberriesAPI();
		$this->hl = new HighloadApi(self::HL_BLOCK_ID);
		
		$this->logger = new TsLogger("/wb/" . __CLASS__ . "/");
		$this->workers = new WorkersChecker(__CLASS__);
		
		$this->panelDB = new DBPanel();
	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
		Loader::includeModule("highloadblock");
		Loader::includeModule("panel.manager");
    }

	public function run(){

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		if (!$this->workers->checkStatus()) {
			$this->logger->log("LOG", "Обработчик занят");
			exit();
		}
		
		$this->workers->updateStatus("Y");
		
		// получаем заказы с вб
		$this->getOrders();
		
		// получаем заказы битрикса 
		$this->getOrdersBX();
		
		$this->prepareOrders();
		$this->updateOrders();

		$this->workers->updateStatus("N");
	}
	
	
	protected function getOrders(){
		$this->logger->log("LOG", "Получаем заказы с wb");
		$arFilter = [
			//"status" => $arFilter["status"],
			"since" => date("Y-m-dT00:00:00Z"), //"2024-11-01T00:00:00Z",
			//"to" => date("Y-m-d"),
		];
		$dateFrom = date("Y-m-d") . "T00:00:00";
		//$dateFrom = "1969-12-12T14:19:00";  
		$dateFrom = date("Y-m-d");
		
		$orders = $this->wb->getOrdersFBO($dateFrom, 1); 
		file_put_contents("/home/bitrix/logs/wb/UpdateOrderFBO/orders_" . date("Y_m_d") . ".txt", print_r([date("Y-m-d H:i:s"), $dateFrom, $orders], true), 8);
		foreach($orders as $arItem){
			$this->orders[] = [
				"ORDER_ID" => $arItem["incomeID"],
				"SOURCE" => ($arItem["warehouseType"] == "Склад продавца" ? "WB_FBS" : "WB_FBO"),
				"ORDER_NUMBER" => $arItem["incomeID"],
				"STATUS" => "",
				"CREATED" => $arItem["date"],
				"PRODUCT_ID" => false,
				"PRODUCT_NAME" => false,
				"PRODUCT_ARTICLE_MP" => $arItem["supplierArticle"],
				"PRODUCT_ARTICLE" => false,
				"CURRENCY" => "RUB",
				"PRICE" => $arItem["priceWithDisc"],
				"QUANTITY" => 1,
				"HASH" => $arItem["srid"],
				"CANCELED" => ($arItem["isCancel"] ? 1 : 0),
				"SALE" => $arItem["spp"],
			];
		}
		$this->logger->log("LOG", "Полученные заказы", $this->orders);
	}
	
	protected function getOrdersBX(){
		if(count($this->orders) == 0) return;
		$arFilter = [
			"UF_SOURCE" => ["WB_FBO", "WB_FBS"],
			"UF_HASH" => array_column($this->orders, "HASH"),
		];
		$order = $this->hl->getList($arFilter);
		
		foreach($order as $arItem){
			if($this->ordersBX[$arItem["UF_HASH"]]){
				// удаляем
				$result = $this->hl->remove($arItem["ID"]);
				if (!$result->isSuccess()) {
					$errors = $result->getErrorMessages();
					$this->logger->log("ERROR", "Ошибка удаления заказа", [$result->getErrorMessages(), $arItem]);
				} else {
					$this->logger->log("LOG", "Удалили заказ", [$arItem]);
				}
				continue;
			}
			$this->ordersBX[$arItem["UF_HASH"]] = $arItem;
		}

	}
	
	protected function prepareOrders(){
		if(count($this->orders) == 0) return;
		// ищем товары. добавляем артикул основной и ID
		$article = array_column($this->orders, "PRODUCT_ARTICLE_MP");
		
		$arSelect = Array("ID", "NAME", "PROPERTY_CML2_ARTICLE", "PROPERTY_WBARTICLE2",);
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			"PROPERTY_WBARTICLE2" => $article
		);

		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		$arProduct = [];
		while ($el = $result->GetNext()){
			$arProduct[$el["PROPERTY_WBARTICLE2_VALUE"]] = [
				"ID" => $el["ID"],
				"NAME" => $el["NAME"],
				"ARTICLE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
			];
			
		}

		foreach($this->orders as &$arItem){
			if($arProduct[$arItem["PRODUCT_ARTICLE_MP"]]){
				$arItem["PRODUCT_ID"] = $arProduct[$arItem["PRODUCT_ARTICLE_MP"]]["ID"];
				$arItem["PRODUCT_NAME"] = $arProduct[$arItem["PRODUCT_ARTICLE_MP"]]["NAME"];
				$arItem["PRODUCT_ARTICLE"] = $arProduct[$arItem["PRODUCT_ARTICLE_MP"]]["ARTICLE"];
			}
		}
		unset($arItem);
	}
	

	protected function updateOrders(){
		if(count($this->orders) == 0) return;
		//prent($this->orders);
		

		$arAdd = [];
		foreach($this->orders as $arItem){
			if($this->ordersBX[$arItem["HASH"]]){
				$ID = $this->ordersBX[$arItem["HASH"]]["ID"];
				$arFields = [
					"UF_SOURCE" => $arItem["SOURCE"],
					"UF_CANCELED" => $arItem["CANCELED"],
					"UF_PRICE" => $arItem["PRICE"],
					"UF_QUANTITY" => $arItem["QUANTITY"],
					"UF_CURRENCY" => $arItem["CURRENCY"],
					"UF_SALE" => $arItem["SALE"],
				];
				$result = $this->hl->update($ID, $arFields);
				if (!$result->isSuccess()) {
					$errors = $result->getErrorMessages();
					$this->logger->log("ERROR", "Ошибка обновления заказа", [$result->getErrorMessages(), $ID, $arFields]);
				} else {
					$this->logger->log("LOG", "Обновили заказ", [$result->getId(), $arFields]);
				}
				unset($this->ordersBX[$arItem["HASH"]]);
			}else{
				$arFields = [
					"UF_ORDER_ID" => $arItem["ORDER_ID"],
					"UF_SOURCE" => $arItem["SOURCE"],
					"UF_ORDER_NUMBER" => $arItem["ORDER_NUMBER"],
					"UF_CANCELED" => $arItem["CANCELED"],
					"UF_CREATED" => ConvertTimeStamp(strtotime(str_replace("T", " ", $arItem["CREATED"])), "FULL"),
					"UF_PRODUCT" => $arItem["PRODUCT_ID"],
					"UF_PRODUCT_NAME" => $arItem["PRODUCT_NAME"],
					"UF_PRODUCT_ARTICLE" => $arItem["PRODUCT_ARTICLE"],
					"UF_PRODUCT_ARTICLE_MP" => $arItem["PRODUCT_ARTICLE_MP"],
					"UF_PRICE" => $arItem["PRICE"],
					"UF_QUANTITY" => $arItem["QUANTITY"],
					"UF_CURRENCY" => $arItem["CURRENCY"],
					"UF_HASH" => $arItem["HASH"],
					"UF_SALE" => $arItem["SALE"],
				];
				$result = $this->hl->add($arFields);
				
				if (!$result->isSuccess()) {
					$errors = $result->getErrorMessages();
					$this->logger->log("ERROR", "Ошибка добавления заказа", [$result->getErrorMessages(), $arFields]);
				} else {
					//$id = $result->getId();
					$this->logger->log("LOG", "Добавили заказ", [$result->getId(), $arFields]);
				}

				$arAdd[] = [
					"ORDER_ID" => $arItem["ORDER_ID"],
					"ORDER_BASKET_ID" => $arItem["HASH"],
					"PRODUCT_ARTICLE" => $arItem["PRODUCT_ARTICLE"],
					"SOURCE" => $arItem["SOURCE"],
				];
			}
			
		}
		
		// удаляем то что осталось вдруг
		foreach($this->ordersBX as $arItem){
			$result = $this->hl->remove($arItem["ID"]);
			if (!$result->isSuccess()) {
				$errors = $result->getErrorMessages();
				$this->logger->log("ERROR", "Ошибка удаления заказа", [$result->getErrorMessages(), $arItem]);
			} else {
				$this->logger->log("LOG", "Удалили заказ", [$arItem]);
			}
		}
		
		if(count($arAdd) > 0){
			// фиксируем цены себестоимости
			// ищем цены себестоимости
			/*$arArticle = array_column($arAdd, "PRODUCT_ARTICLE");
			$strSql = "SELECT * FROM current_cost_ms WHERE model IN ('".implode("','", $arArticle)."')";

			$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
			
			$arPrices = [];
			while ($row = $results->Fetch()){
				$arPrices[$row["model"]] = $row["cost"]; 
			}*/
			
			$arPrices = [];
			
			// FBO
			$arArticle = $arArticleFBS = [];
			
			foreach($arAdd as $v){
				if($v["SOURCE"] == "WB_FBO"){
					if(!in_array($v["PRODUCT_ARTICLE"], $arArticle))
						$arArticle[] = $v["PRODUCT_ARTICLE"];
				}else{
					if(!in_array($v["PRODUCT_ARTICLE"], $arArticleFBS))
						$arArticleFBS[] = $v["PRODUCT_ARTICLE"];
				}
				
			}
			
			if(count($arArticle) > 0){				
				$result = $this->panelDB->query("SELECT model as article, quantity as price FROM ms_turnover WHERE model IN ('" . implode("','", $arArticle) . "')");
				$res = $this->panelDB->fetchAll($result);
				foreach($res as $arItem){
					//$arPrices[$arItem["article"]]["price"] = $arItem["price"];
					$arPrices[$arItem["article"]] = ["price" => $arItem["price"]];
				}
			}
			
			if(count($arArticleFBS) > 0){ 
				$data = [
					"article" => implode(",", $arArticleFBS),
					"website" => "wb",
					"price" => "discount",
					"price_competitors" => "N",
					"price_competitors_act" => "N",
					"remove_duplicates" => "Y",
					"hide_rrc" => "N",
					"without_competitors" => "N",
					"only_active" => "Y",
					"ajax" => "Y",
				];
				$params = "";
				foreach($data as $k => $v){
					$params .= " {$k}={$v}";
				}

				$url = "/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/analysis/get_list.php {$params}";
				file_put_contents("/home/bitrix/logs/___22222.txt", print_r(["date" => date("Y-m-d H:i:s"), "url" => $url], true), 8);
				try{
					$json = shell_exec("/usr/bin/php81 -f {$url}");
					file_put_contents("/home/bitrix/logs/___22222.txt", print_r(["json" => $json], true), 8);
					$tmp = json_decode($json, true);
					foreach($tmp as $k => $v){
						$arPrices[$v["article"]] = $v;
					}
					
				}catch(Exception $e){
				}
			}
			
			
			foreach($arAdd as $arItem){
				if($arPrices[$arItem["PRODUCT_ARTICLE"]]){
					$in = array(
						"ORDER_ID" => "'".addslashes($arItem["ORDER_ID"])."'",
						"ORDER_BASKET_ID" => "'".addslashes($arItem["ORDER_BASKET_ID"])."'",
						"PRICES" => "'".addslashes(serialize($arPrices[$arItem["PRODUCT_ARTICLE"]]))."'",
					);
					
					$strSql = "SELECT * FROM ci_order_price WHERE ORDER_ID = '{$arItem["ORDER_ID"]}' AND ORDER_BASKET_ID = '{$arItem["ORDER_BASKET_ID"]}'";
					//if (!$row = $results->Fetch()){
					if (!$row = $this->db->Query($strSql, false, $err_mess.__LINE__)->Fetch()){
						$this->db->Insert("ci_order_price", $in, $err_mess.__LINE__);
					}
				}
			}
		}

		
	}
	
}

(new UpdateOrderFBO())->run();
?>
