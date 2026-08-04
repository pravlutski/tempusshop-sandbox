#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");
CModule::IncludeModule('panel.manager');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');

$syncHelper = new SyncHelper();
$triggers = new TsTriggers();
$logger = new TsLogger("/onliner/getOrders/");
$workers = new WorkersChecker("onlinerGetOrder");

if (!$workers->checkStatus()) {
	$logger->log("LOG", "Обработчик занят");
	print_r("Обработчик занят\n");
	exit();
}

$logger->log("LOG", "Запуск обработчика");

if ( $argv[1] != 'N'){
	$workers->updateStatus("Y");
}

use Bitrix\Main\Context,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem;

global $USER;

Bitrix\Main\Loader::includeModule("sale");
Bitrix\Main\Loader::includeModule("catalog");

if (!class_exists('OnlinerCart_API')){
	require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/api_onliner_cart.php');
}

$obj = new OnlinerCart_API;
$order = $obj->getListOrder(1, 100, "new", "status_change_log,positions");

$order = json_decode($order, true);

foreach($order["orders"] as $key => $arItem){

	$order_key = $arItem["key"];

	$data = array(
		"status" => "processing",
	);

	$params = json_encode_cyr($data);
	$res = $obj->setOrderStatus($order_key, $params);

	if(isset($res["errors"])){
		//пишем в лог
		$res = json_decode($res, true);

		$logger->log("ERROR", "Onliner KEY - {$order_key}. Ошибка при переводе в статус 'processing'. params  - {$params} . res - " . print_r($res, true));

		$triggers->SetError(["Onliner. " . ". Onliner KEY - {$order_key}. Ошибка при переводе в статус 'processing'. params  - {$params} . res - " . serialize($res) . "\r\n"]);
		$triggers->SendTriggerErrors();
	}

}


$order = $obj->getListOrder(1, 100, "processing", "status_change_log,positions");

$logger->log("LOG", "order_processing - " . print_r($order, true));

$order = json_decode($order, true);

foreach($order["orders"] as $key => $arItem){

	$order_key = $arItem["key"];

	//если заказ создан более 35 минут заказа то переводим в подтвержден
	//if($arItem["payment"]["type"] != "online"){
		$date_from_timestamp = strtotime($arItem["process_deadline"]);
	//	if($date_from_timestamp < time() + 60 * 10){
			$data = array(
				"status" => "confirmed",
			);
			$params = json_encode_cyr($data);
			$res = $obj->setOrderStatus($order_key, $params);

			if(isset($res["errors"])){
				//пишем в лог
				$res = json_decode($res, true);
				$logger->log("ERROR", "Заказ {$orderId}. Onliner KEY - {$order_key}. Ошибка при переводе в статус 'confirmed'. " . print_r($res, true));
				$triggers->SetError(["Onliner. " . ". Заказ {$orderId}. Onliner KEY - {$order_key}. Ошибка при переводе в статус 'confirmed'. " . serialize($res) . "\r\n"]);
				$triggers->SendTriggerErrors();
			}

	//	}
	//}
}


//создаем заказы
//$fp = fopen($filepath, 'w');
//file_put_contents($filepath, date("Y-m-d H:i:s") . " - " . $order . "\r\n", FILE_APPEND);
$order = $obj->getListOrder(1, 100, "confirmed", "status_change_log,positions");
$order = json_decode($order, true);

$logger->log("LOG", "order_confirmed - " . print_r($order, true));

foreach($order["orders"] as $key => $arItem){
	$order_key = $arItem["key"];
	$arFilter = Array(
	   "PROPERTY_VAL_BY_CODE_ONLINER_ORDER_KEY" => $order_key,
	);

	$db_sales = CSaleOrder::GetList(array("DATE_INSERT" => "ASC"), $arFilter);
	if($ar_sales = $db_sales->Fetch()){
		continue;
	}

	$logger->log("LOG", "ONLINER KEY - {$order_key}. Создаем заказ - " . print_r($arItem, true));

	if(!$arItem["contact"]){

		$logger->log("ERROR", "ONLINER KEY - {$order_key}. Нет информации по пользователю. " . print_r($arItem, true));

		$triggers->SetError(["Onliner. " . ". ONLINER KEY - {$order_key}. Нет информации по пользователю. " . serialize(json_decode($arItem, true)) . "\r\n"]);
		$triggers->SendTriggerErrors();
		continue;
	}

	$phone = str_replace(array(" ", "-"), "", $arItem["contact"]["phone"]);
	//$name = $arItem["contact"]["name"] . ($arItem["contact"]["first_name"] ? " " . $arItem["contact"]["first_name"] : "") . ($arItem["contact"]["middle_name"] ? " " . $arItem["contact"]["middle_name"] : "");
	$name = $arItem["contact"]["name"];
	$comment = $arItem["comment"];

	//$phone = "+375291192952";

	//$user_id = 1;
	//создаем пользователя или берем старого и подменяем Покупателя
	$siteId = "s2";
	$user = new CUser;
	var_dump( 'here' );

	if($phone !== false){
		$phone_login = preg_replace("/[^0-9]/", '', $phone);
		$rsUser = CUser::GetByLogin($phone_login);
		if(!$arUser = $rsUser->Fetch()){
			//если нет пользователя то создаем
			$arAddUser = Array(
				"NAME"              => $arItem["contact"]["first_name"],
				"LAST_NAME"         => $arItem["contact"]["last_name"],
				"SECOND_NAME"		=> $arItem["contact"]["middle_name"],
				"EMAIL"             => $arItem["contact"]["email"],
				"LOGIN"             => $phone_login,
				"PERSONAL_PHONE"	=> $phone,
				"LID"               => $siteId,
				"ACTIVE"            => "Y",
				"GROUP_ID"          => array(3,5,4),
				"PASSWORD"          => "8lM4Ee",
				"CONFIRM_PASSWORD"  => "8lM4Ee",
			);
			
			$ID = $user->Add($arAddUser);
			if(intval($ID) > 0){
				$user_id = $ID;
			}else{ 
				$logger->log("ERROR", "Onliner KEY - {$order_key}. Попытка создания пользовтаеля 1. Не удалось создать пользователя." . print_r($res, true) . " arAddUser - " , print_r($arAddUser, true) . " result - " . serialize($user->LAST_ERROR));
				$arAddUser["EMAIL"] = "";
				$ID = $user->Add($arAddUser);
				if(intval($ID) > 0){
					$user_id = $ID;
				}else{

					$logger->log("ERROR", "Onliner KEY - {$order_key}. Не удалось создать пользователя." . print_r($res, true) . " arAddUser - " , print_r($arAddUser, true));

					$triggers->SetError(["Onliner. " . ". Onliner KEY - {$order_key}. Не удалось создать пользователя. " . serialize($res) . " USER - " . serialize($arAddUser) . "\r\n result - " . serialize($user->LAST_ERROR)]);
					$triggers->SendTriggerErrors();
				}
			}
		}else{
			$user_id = $arUser["ID"];
		}
	}



	$currencyCode = "BYN";

	// Создаёт новый заказ
	$order = Order::create($siteId, $user_id);
	$order->setPersonTypeId(1);
	$order->setField('CURRENCY', $currencyCode);
	if ($comment) {
		$order->setField('USER_DESCRIPTION', $comment); // Устанавливаем поля комментария покупателя
	}
	$order->setField('COMMENTS', "ONLINER");
	// Создаём корзину
	$basket = Basket::create($siteId);
	foreach($arItem["positions"] as $k => $arBasket){
		//достаем DETAIL_PAGE_URL
		$productInfo = array();
		$elementsIterator = CIBlockElement::GetList(
			array(),
			array(
				'ID' => $arBasket["position_id"],
			),
			false,
			false,
			array(
				'ID',
				'XML_ID',
				'NAME',
				'DETAIL_PAGE_URL',
			)
		);
		if (($element = $elementsIterator->GetNext(false, false))) {
			$productInfo = array(
				'ID' => $element['ID'],
				'XML_ID' => $element['XML_ID'],
				'NAME' => $element['NAME'],
				'DETAIL_PAGE_URL' => $element['DETAIL_PAGE_URL'],
			);
		}

		$item = $basket->createItem('catalog', $arBasket["position_id"]);
		$item->setFields(array(
			'QUANTITY' => $arBasket["quantity"],
			'BASE_PRICE' => $arBasket["position_price"]["amount"],
			'PRICE' => $arBasket["position_price"]["amount"],
			'CURRENCY' => $currencyCode,
			'LID' => $siteId,
			//'PRODUCT_PROVIDER_CLASS' => '\Bitrix\Catalog\Product\CatalogProvider',
			'CUSTOM_PRICE' => 'Y',
			'NAME' => $productInfo['NAME'],//$arBasket["product"]["full_name"],
			//'XML_ID' => $productInfo['XML_ID'],
			'CATALOG_XML_ID' => 'aspro_mshop_catalog_s1',
			'PRODUCT_XML_ID' => $productInfo['XML_ID'],
			'DETAIL_PAGE_URL' => $productInfo['DETAIL_PAGE_URL']
		));
	}

	$order->setBasket($basket);

	if($arItem["delivery"]){
		if($arItem["delivery"]["type"] == "courier") $delivery_id = 21; else $delivery_id = 22;
		// создаём отгрузку заказа
		$shipmentCollection = $order->getShipmentCollection();
		$shipment = $shipmentCollection->createItem(
			\Bitrix\Sale\Delivery\Services\Manager::getObjectById($delivery_id)
		);
		$shipmentItemCollection = $shipment->getShipmentItemCollection();

		$service = \Bitrix\Sale\Delivery\Services\Manager::getById($delivery_id);
		$deliveryData = [
		  'DELIVERY_ID' => $service['ID'],
		  'DELIVERY_NAME' => $service['NAME'],
		  'ALLOW_DELIVERY' => 'Y',
		  'PRICE_DELIVERY' => $arItem["delivery"]["price"]["amount"],
		  'CUSTOM_PRICE_DELIVERY' => 'Y'
		];
		$shipment->setFields($deliveryData);

		foreach ($basket as $basketItem) {
			$item = $shipmentItemCollection->createItem($basketItem);
			$item->setQuantity($basketItem->getQuantity());
		}

		$deliveryAddress = $arItem["delivery"]["city"];

		if($arItem["delivery"]["address"])
			$deliveryAddress .= " " . $arItem["delivery"]["address"];

		if($arItem["delivery"]["delivery_comment"])
			$deliveryAddress .= " " . $arItem["delivery"]["delivery_comment"];
	}


	switch ($arItem["payment"]["type"]){
		case "cash":
			$payment_id = 1;
			break;
		case "halva":
			$payment_id = 24;
			break;
		case "terminal":
			$payment_id = 27;
			break;
		default:
			$payment_id = 1;
			break;
	}
	// Создаём оплату со способом #1
	$paymentCollection = $order->getPaymentCollection();
	$payment = $paymentCollection->createItem();
	$paySystemService = PaySystem\Manager::getObjectById($payment_id);
	$payment->setFields(array(
		'PAY_SYSTEM_ID' => $paySystemService->getField("PAY_SYSTEM_ID"),
		'PAY_SYSTEM_NAME' => $paySystemService->getField("NAME"),
		'SUM' => $arItem["order_cost"]["amount"],
	));

	// Устанавливаем свойства
	$propertyCollection = $order->getPropertyCollection();

	$phoneProp = $propertyCollection->getPhone();
	$phoneProp->setValue($phone);

	$nameProp = $propertyCollection->getPayerName();
	$nameProp->setValue($name);

	$addressProperty = $propertyCollection->getAddress();
	$addressProperty->setValue($deliveryAddress);// Устанавливаем адрес

	$onlinerKey = $propertyCollection->getItemByOrderPropertyId(55);
	$onlinerKey->setValue($order_key);

	// Сохраняем
	$order->doFinalAction(true);
	$result = $order->save();
	if ($result->isSuccess()) {
		$orderID = $order->getId();
		$accountNumber = $order->getField('ACCOUNT_NUMBER');
		
		$tradeBindingCollection = $order->getTradeBindingCollection();
		$tpId = null;
		
		foreach ($tradeBindingCollection as $item) {
			$tpId = $item->getField('TRADING_PLATFORM_ID');
			break;
		}

		if (!$tpId) {
			$res = \Bitrix\Sale\TradingPlatform\OrderTable::add(array(
				"ORDER_ID" => $orderID,
				"TRADING_PLATFORM_ID" => 15,
				"EXTERNAL_ORDER_ID" => $accountNumber
			));

			if (!$res->isSuccess()) {
				$logger->log("ERROR", "Заказ для Onliner KEY - {$order_key} не создан. Ошибка источника заказа");
			} else {
				$syncHelper->sendOrder($orderID);
			}
		}
	}else{
		$logger->log("ERROR", "Заказ для Onliner KEY - {$order_key} не создан", $result->getErrorMessages());

		$triggers->SetError(["Onliner. " . ". Заказ для Onliner KEY - {$order_key} не создан. \r\n"]);
		$triggers->SendTriggerErrors();
	}

}


//получаем отмененные
$order = $obj->getListOrder(1, 100, "user_canceled,expired,system_canceled", "positions");
//,expired,system_canceled
$order = json_decode($order, true);

foreach($order["orders"] as $key => $arItem){

	$order_key = $arItem["key"];
	$arFilter = Array(
	   "PROPERTY_VAL_BY_CODE_ONLINER_ORDER_KEY" => $order_key,
	);

	$db_sales = CSaleOrder::GetList(array("DATE_INSERT" => "ASC"), $arFilter);
	if($ar_sales = $db_sales->Fetch()){
		OrderService::setStatusOrder($ar_sales["ID"], "no");
	}
}
$logger->log("LOG", "Конец обработки");
$workers->updateStatus("N");

?>
