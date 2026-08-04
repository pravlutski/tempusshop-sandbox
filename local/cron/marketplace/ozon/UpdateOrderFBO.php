<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

require($_SERVER['DOCUMENT_ROOT'] . '/local/classes/OzonAPI.php');

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
		$this->ozon = new OzonAPI();
		$this->hl = new HighloadApi(self::HL_BLOCK_ID);
		
		$this->logger = new TsLogger("/ozon/" . __CLASS__ . "/");
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
		
		// получаем заказы с озона
		$this->getOrders();
		
		// получаем заказы битрикса 
		$this->getOrdersBX();
		
		$this->prepareOrders();
		$this->updateOrders();

		$this->workers->updateStatus("N");
	}
	
	
	protected function getOrders(){
		$this->logger->log("LOG", "Получаем заказы с озона");
		$arFilter = [
			//"status" => $arFilter["status"],
			"since" => date("Y-m-d") . "T00:00:00Z", //"2024-11-01T00:00:00Z",
			//"to" => date("Y-m-d"),
		];
		$orders = $this->ozon->getOrdersFBO($arFilter);
		file_put_contents("/home/bitrix/logs/ozon/UpdateOrderFBO/orders_" . date("Y_m_d") . ".txt", print_r([date("Y-m-d H:i:s"), $orders], true), 8);

		$i = 0;
		foreach($orders as $arItem){
			if(!is_array($arItem["products"]) || count($arItem["products"]) == 0) continue;
			
			foreach($arItem["products"] as $arProduct){
				$this->orders[$i] = [
					"ORDER_ID" => $arItem["order_id"],
					"SOURCE" => "OZON_FBO",
					"ORDER_NUMBER" => $arItem["order_number"],
					"POSTING_NUMBER" => $arItem["posting_number"],
					"STATUS" => $arItem["status"],
					"CREATED" => $arItem["created_at"],
					"PRODUCT_ID" => false,
					"PRODUCT_NAME" => $arProduct["name"],
					"PRODUCT_ARTICLE_MP" => $arProduct["offer_id"],
					"PRODUCT_ARTICLE" => false,
					"CURRENCY" => $arProduct["currency_code"],
					"PRICE" => $arProduct["price"],
					"QUANTITY" => $arProduct["quantity"],
					"HASH" => md5($arItem["order_id"] . $arProduct["offer_id"]),
					"CANCELED" => ($arItem["status"] == "cancelled" ? 1 : 0),
				];
				$i++;
			}
		}
		$this->logger->log("LOG", "Полученные заказы", $this->orders);
	}
	
	protected function getOrdersBX(){
		if(count($this->orders) == 0) return;
		$arFilter = [
			"UF_SOURCE" => "OZON_FBO",
			//"UF_ORDER_ID" => array_column($this->orders, "ORDER_ID"),
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
		
		$arSelect = Array("ID", "NAME", "PROPERTY_CML2_ARTICLE", "PROPERTY_WBARTICLE",);
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			"PROPERTY_WBARTICLE" => $article
		);

		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		$arProduct = [];
		while ($el = $result->GetNext()){
			$arProduct[$el["PROPERTY_WBARTICLE_VALUE"]] = [
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
					"UF_STATUS" => $arItem["STATUS"],
					"UF_PRICE" => $arItem["PRICE"],
					"UF_QUANTITY" => $arItem["QUANTITY"],
					"UF_CURRENCY" => $arItem["CURRENCY"],
					"UF_CANCELED" => $arItem["CANCELED"],
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
					"UF_POSTING_NUMBER" => $arItem["POSTING_NUMBER"],
					"UF_STATUS" => $arItem["STATUS"],
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
				];
				$result = $this->hl->add($arFields);
				
				if (!$result->isSuccess()) {
					$errors = $result->getErrorMessages();
					$this->logger->log("ERROR", "Ошибка добавления заказа", [$result->getErrorMessages(), $arFields]);
				} else {
					$ID = $result->getId();
					$this->logger->log("LOG", "Добавили заказ", [$result->getId(), $arFields]);
					
					$arAdd[] = [
						"ID" => $ID,
						"ORDER_ID" => $arItem["ORDER_ID"],
						"ORDER_BASKET_ID" => $arItem["HASH"],
						"PRODUCT_ARTICLE" => $arItem["PRODUCT_ARTICLE"],
						"PRODUCT_ARTICLE_MP" => $arItem["PRODUCT_ARTICLE_MP"],
					];
				}


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
			$arPrices = [];
			$arArticle = array_column($arAdd, "PRODUCT_ARTICLE");
			
			$result = $this->panelDB->query("SELECT model as article, quantity as price FROM ms_turnover WHERE model IN ('" . implode("','", $arArticle) . "')");
			$res = $this->panelDB->fetchAll($result);
			foreach($res as $arItem){
				//$arPrices[$arItem["article"]]["price"] = $arItem["price"];
				$arPrices[$arItem["article"]] = ["price" => $arItem["price"]];
			}
			
			$arMatch = [];
			foreach($arAdd as $v){
				$arMatch[$v["PRODUCT_ARTICLE_MP"]] = [
					"ID" => $v["ID"],
					"PRODUCT_ARTICLE" => $v["PRODUCT_ARTICLE"],
				];
			}
			
			$arArticleMP = array_column($arAdd, "PRODUCT_ARTICLE_MP");
			$arChunk = array_chunk($arArticleMP, 1000);
			//prent($arPrices,0,1);
			foreach($arChunk as $chunk){
				$arFilter = [
					"offer_id" => $chunk,
				];
				$prices = $this->ozon->getPrice($arFilter);
				file_put_contents("/home/bitrix/logs/ozon/UpdateOrderFBO/getPrice_" . date("Y_m_d") . ".txt", print_r([date("Y-m-d H:i:s"), $prices], true), 8);
				if(is_array($prices["result"]) && is_array($prices["result"]["items"])){
					foreach($prices["result"]["items"] as $v){
						if(isset($arMatch[$v["offer_id"]]) && is_array($v["price"])){
							$article = $arMatch[$v["offer_id"]]["PRODUCT_ARTICLE"];
							$ID = $arMatch[$v["offer_id"]]["ID"];
							
							//$arPrices[$article]["marketing_seller_price"] = $v["price"]["marketing_seller_price"];
							//$arPrices[$article]["marketing_price"] = $v["price"]["marketing_price"];
							$spp = ((floatval($v["price"]["marketing_seller_price"]) - floatval($v["price"]["marketing_price"])) / floatval($v["price"]["marketing_seller_price"])) * 100;
						
							$arFields = [
								"UF_SALE" => $spp,
							];
							$result = $this->hl->update($ID, $arFields);
							
							file_put_contents("/home/bitrix/logs/ozon/UpdateOrderFBO/getPrice_" . date("Y_m_d") . ".txt", print_r([date("Y-m-d H:i:s"), $arFields, $ID], true), 8);
						}
						

					} 
					
				}
			}
			
			foreach($arAdd as $arItem){
				if($arPrices[$arItem["PRODUCT_ARTICLE"]]){
					$in = array(
						"ORDER_ID" => "'".addslashes($arItem["ORDER_ID"])."'",
						"ORDER_BASKET_ID" => "'".addslashes($arItem["ORDER_BASKET_ID"])."'",
						//"PRICES" => "'".addslashes(serialize($arPrices[$arItem["PRODUCT_ARTICLE"]]))."'",
						"PRICES" => "'".addslashes(serialize($arPrices[$arItem["PRODUCT_ARTICLE"]]))."'",
					);
					
					$strSql = "SELECT * FROM ci_order_price WHERE ORDER_ID = '{$arItem["ORDER_ID"]}' AND ORDER_BASKET_ID = '{$arItem["ORDER_BASKET_ID"]}'";
					//$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
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
