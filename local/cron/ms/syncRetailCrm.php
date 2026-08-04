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

if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("currency") || !CModule::IncludeModule("catalog") || !CModule::IncludeModule("panel.manager")) return;
CModule::IncludeModule("crm_courier");
CModule::IncludeModule("intaro.retailcrm");
global $DB;


$triggers = new TsTriggers();
$logger = new TsLogger("/ms/syncRetailCrm/");
$workers = new WorkersChecker("syncRetailCrm");

if (!$workers->checkStatus()) {
	$logger->log("LOG", "Обработчик занят");
	exit();
}
$logger->log("LOG", "Запуск обработчика");

$workers->updateStatus("Y");

$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

$strSql = "SELECT val.VALUE as PHONE, pr.USER_ID as USER_ID
	FROM
		b_sale_order_props_value val
	LEFT JOIN
		b_sale_order pr
	ON val.ORDER_ID=pr.ID
	WHERE
		val.ORDER_PROPS_ID = '3'";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	if(strlen($row["PHONE"]) >= 9){
		$phone = preg_replace("/[^0-9]/", '', $row["PHONE"]);
		$phone = substr($phone, -9);
		$arResult["CIENTS"][$phone] = $row["USER_ID"];
	}
}

$strSql = "SELECT ID,XML_ID FROM b_iblock_element WHERE IBLOCK_ID = '16'";
$arElementIDs = array();
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arElementIDs[$row["XML_ID"]] = $row["ID"];
}

$strSql = "SELECT * FROM ci_sync_ms";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["SYNC_MS"][$row["ORDER_NUMBER"]] = $row["ORDER_NUMBER"];
}

$objMS = new MoyskladAPI("s2");
$objMS->getRetailDemand(0, true);
$res = $objMS->MSPosition;

//проходим по всем продажам
foreach($res as $key => &$arItem){
	if($arItem["customerOrder"]) unset($res[$key]);
}

unset($arItem);

//проходим по всем продажам
foreach($res as $key => &$arItem){
	//получаем позиции продаж и товары
	if($arItem["id"] != "3f500e08-f69d-11eb-0a80-0499000dfda9"){
	//	continue;
	}

	$number = "MS" . $arItem["name"];
	if($arResult["SYNC_MS"][$number]) continue;

	$id = array_pop(explode("/", $arItem["agent"]["meta"]["href"]));

	if($id == "6f7995ba-180c-11ea-0a80-00b30004eb01") continue;//если розничный покупатель
	sleep(1);
	$arItem["CLIENT"] = $objMS->getAgent($id);

	sleep(1);
	$rsPos = $objMS->getRetailPositions($arItem["id"]);

	$rsItems = array();
	foreach($rsPos as $k => $arPos){
		$id = array_pop(explode("/", $arPos["assortment"]["meta"]["href"]));
		//prent($id);
		sleep(1);
		$arItem["BASKET"][$k] = $arPos;
		$arItem["BASKET"][$k]["PRODUCT"] = $objMS->getProductID($id);
	}

}
unset($arItem);

//prent($res);
$arOrder = array();
foreach($res as $key => $arItem){
	if($arItem["BASKET"] && $arItem["CLIENT"]){
		$phone = preg_replace("/[^0-9]/", '', $arItem["CLIENT"]["phone"]);
		$phone = substr($phone, -9);
		$arName = explode(" ", $arItem["CLIENT"]["name"]);
        $order = array(
            'number'          => "MS" . $arItem["name"],
            'orderType'       => "eshop-individual",
			//'orderMethod'			=> "shop",
			'orderMethod'			=> "registratsiia-klienta-v-pl",
            //'status'          => 'new',
			'status'          => 'complete',
            'customerComment' => '',
            'managerComment'  => '',
            "lastName" => $arName[1],
            "firstName" => $arName[0],
            "patronymic" => $arName[2],
            "email" => $arItem["CLIENT"]["email"],
            "phone" => $arItem["CLIENT"]["phone"],
			"phone_loyalty" => $phone,
			//'privilegeType'  => 'loyalty_level',
			"payments" => array(
				Array(
					"type" => "cash",
					"status" => "not-paid",
					"amount" => $arItem["cashSum"] / 100,
					//'externalId' => RCrmActions::generatePaymentExternalId(1),
				),

			),
			"delivery" => array(
				Array(
					"code" => "misk-pikup",
					"cost" => 0,
				),
			),
        );
		if($arResult["CIENTS"][$phone]){
			// $order["customer"] = array('externalId' => $arResult["CIENTS"][$phone]);
		//	$order["privilegeType"] = "loyalty_level";
		}
		$basket = array();
		foreach($arItem["BASKET"] as $k => $arBasket){

			$initialPrice = $arBasket["price"] / 100;
			if($arBasket["discount"] > 0){
				//$discountManualAmount = $initialPrice - $initialPrice * $arBasket["discount"] / 100;
				$discountManualAmount = $initialPrice * $arBasket["discount"] / 100;
				$discountManualPercent = $arBasket["discount"];
			}else{
				$discountManualAmount = 0;
				$discountManualPercent = 0;
			}

			$basket[] = array(
				"quantity" => $arBasket["quantity"],
				"offer" => array(
					"externalId" => $arElementIDs[$arBasket["PRODUCT"]["externalCode"]],
				),
				"productName" => $arBasket["PRODUCT"]["name"],
				"discountManualPercent" => $discountManualPercent,
				//"discountManualAmount" => $discountManualAmount,
				"initialPrice" => $initialPrice,
				"vatRate" => ($arBasket["vat"] > 0 ? $arBasket["vat"] : "none"),
			);
		}

		$order["items"] = $basket;
		//["cashSum"] / 100.
		$arOrder[] = $order;

	}
}

$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());
foreach($arOrder as $key => $arItem){
	//continue;
	//if($key > 0) continue;
	$logger->log("LOG", "Создание заказа в црм", $arItem);
	
	$res = $api->ordersCreate($arItem, "tempus-by-new");
	$res = objectToArray($res);
	
	$logger->log("LOG", "Ответ от црм", [$arItem['number'], $res]);
	var_dump($res);
	
	if($res["response"]["success"] && $res["response"]["id"] > 0){

		//добавляем в программу лоялности
		if($arItem["phone_loyalty"] && $res["response"]["order"]["customer"]["id"]){
			$ar = array(
				"phoneNumber" => $arItem["phone_loyalty"],
				"customer" => array(
					"id" => $res["response"]["order"]["customer"]["id"],
				),
			);
			$resCustomer = $api->loyaltyCreate($ar);
			$resCustomer = objectToArray($resCustomer);

			//применяем привилегию. т.к. если при создании заказа передавать иногда ошибки
			$arLoyalty = array(
				"privilegeType" => "loyalty_level",
				"id" => $res["response"]["id"],
			);
			$resLoyalty = $api->ordersEdit($arLoyalty, "id", "tempus-by-new");
			$resLoyalty = objectToArray($resLoyalty);

			if($resLoyalty["response"]["success"] && $resLoyalty["response"]["id"] > 0){

			}else{
				/*$arLog = array(
					"event" => "ER",
					"text" => "Ошибка обновления заказа из МС в CRM",
					"detail" => serialize(array("ORDER" => $arItem, "RESPONSE_CRM" => $res, "RESPONSE_CRM_LOYALTY" => $resLoyalty, "RESPONSE_CRM_CUSTOMER" => $resCustomer, "IDs" => $in)),
				);
				CLog::add2log($arLog);*/
				$logger->log("ERROR", "Ошибка обновления заказа из МС в CRM", [
					"ORDER" => $arItem, 
					"RESPONSE_CRM" => $res, 
					"RESPONSE_CRM_LOYALTY" => $resLoyalty, 
					"RESPONSE_CRM_CUSTOMER" => $resCustomer, 
					"IDs" => $in
				]);
			}
		}

		$in = array(
			"ORDER_NUMBER"	=> "'".addslashes($arItem["number"])."'",
			"ORDER_ID_CRM"	=> "'".addslashes($res["response"]["id"])."'",
		);
		$DB->Insert("ci_sync_ms", $in, $err_mess.__LINE__);


	}else{
		$logger->log("ERROR", "Ошибка создания заказа из МС в CRM", ["ORDER" => $arItem,"ERROR_CRM" => $res]);
		/*$arError = array(
			"event" => "ER",
			"text" => "Ошибка создания заказа из МС в CRM",
			"detail" => serialize(array("ORDER" => $arItem,"ERROR_CRM" => $res)),
		);
		CLog::add2log($arError);*/
	}
	//die;
}

//возвраты

$arResult = array();

$strSql = "SELECT * FROM ci_sync_ms WHERE ORDER_ID_CRM IS NOT NULL AND CANCELED = 'N'";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["SYNC_MS"][$row["ORDER_NUMBER"]] = $row;
}
sleep(1);
$objMS->MSPosition = array();
$objMS->getRetailSalesReturn(0, true);

$resRetail = $objMS->MSPosition;

$arIDs = array();
foreach($resRetail as $key => &$arItem){
	$demand_id = end(explode("/", $arItem["demand"]["meta"]["href"]));
	if($demand_id){
		$arIDs[] = $demand_id;
	}else{
		unset($resRetail[$key]);
	}
}
unset($arItem);

if(count_($arIDs) > 0){
	$url = "https://api.moysklad.ru/api/remap/1.2/entity/retaildemand/?filter=id=" . implode(";id=", $arIDs);
	sleep(1);
	$resDemand = $objMS->customRequest($url);

	foreach($resDemand["row"] as $key => $arItem){
		$demand_id = "MS" . $arItem["name"];
		$_arDemand[$arItem["id"]] = $arItem["name"];

	}
}

foreach($resRetail as $key => &$arItem){
	$demand_id = end(explode("/", $arItem["demand"]["meta"]["href"]));
	if($_arDemand[$demand_id]){

		$arItem["DEMAND_ID"] = "MS" . $_arDemand[$demand_id];
		if(!$arResult["SYNC_MS"]["MS" . $_arDemand[$demand_id]]) unset($resRetail[$key]);

	}else{
		unset($resRetail[$key]);
	}
	//
}
unset($arItem);

//prent($resRetail,0,1);
//die;
foreach($resRetail as $key => $arItem){
	$arDemand = $arResult["SYNC_MS"][$arItem["DEMAND_ID"]];
	if(!$arDemand){
		continue;
	}
	$ar = array(
		"status" => "noorder",
		"privilegeType" => "none",
		"id" => $arDemand["ORDER_ID_CRM"],
	);
	$resCrm = $api->ordersEdit($ar, "id", "tempus-by");
	$resCrm = objectToArray($resCrm);

	if($resCrm["response"]["success"] && $resCrm["response"]["id"] > 0){
		$in = array(
			"CANCELED" => "'Y'",
		);
		$DB->Update("ci_sync_ms", $in, "WHERE ID = '".$arDemand["ID"]."'", $err_mess.__LINE__);
	}else{
		/*$arLog = array(
			"event" => "ER",
			"text" => "Ошибка при отмене заказа из МС в CRM",
			"detail" => serialize(array("ORDER" => $arItem, "RESPONSE_CRM" => $resCrm)),
		);*/
		CLog::add2log($arLog);
		$logger->log("ERROR", "Ошибка при отмене заказа из МС в CRM", ["ORDER" => $arItem, "RESPONSE_CRM" => $resCrm]);
	}
}

$workers->updateStatus("N");

?>
