#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("local_cron_other_check_orders_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(0);
//if (function_exists('ini_set')) ini_set('memory_limit','1512M');

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule("crm_courier");
CModule::IncludeModule("intaro.retailcrm");

global $DB;

$obj = new CCourier();
$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

//	$response = $api->getListOrder(20, 1, array("numbers" => array(205243)));
//	$res = objectToArray($response);
//	prent($res,0,1);
//	die;
$objService = new OrderService;
//запрашиваем заказы в црм

$limitTime = 1800;

$arOrderCRM = $arOrderID = $arError = [];
$cntStep = 100;
for($i = 1; $i <= 10; $i++){
	$response = $api->getListOrder($cntStep, $i);
	$res = objectToArray($response);

	foreach($res["response"]["orders"] as $key => $arItem){
		$time_diff = time() - strtotime($arItem["statusUpdatedAt"]);

		if($time_diff < $limitTime) continue;

		$arOrderCRM[$arItem["number"]] = array(
			"id" => $arItem["id"],
			"number" => $arItem["number"],
			"status" => $arItem["status"],
			"phone" => $arItem["phone"],
			"createdAt" => $arItem["createdAt"],
			"statusUpdatedAt" => $arItem["statusUpdatedAt"],
			"summ" => $arItem["summ"],
			"totalSumm" => $arItem["totalSumm"],
		);

		$arOrderID[$arItem["number"]] = $arItem["number"];
	}
}
file_put_contents("/home/bitrix/logs/check_orders.txt", "arOrderCRM " . print_r($arOrderCRM, true));
file_put_contents("/home/bitrix/logs/check_orders.txt", "arOrderID " . print_r($arOrderID, true), FILE_APPEND);
//$resBX = $objService->getOrderCache(array("ID" => "DESC"), array(), array("nTopCount" => 1000));
$resBX = $objService->getOrderCache(array("ID" => "DESC"), array("ACCOUNT_NUMBER" => array_keys($arOrderCRM)), array());

foreach($resBX as $key => $arItem){
	$bxOrder[$arItem["ORDER_ID"]] = array(
		"ID" => $arItem["ID"],
		"ORDER_NUMBER" => $arItem["ORDER_ID"],
		"STATUS_ID" => $arItem["STATUS_ID"],
		"PHONE" => $arItem["PHONE"],
		"DATE_UPDATE" => $arItem["DATE_UPDATE"],
		"PRICE" => $arItem["PRICE"],
	);

}
file_put_contents("/home/bitrix/logs/check_orders.txt", "bxOrder " . print_r($bxOrder, true), FILE_APPEND);

//ищем все заказы в МС
if(count($arOrderID) > 0){
	//s1
	$objMS = new MoyskladAPI("s1");
	$objMS->MSPosition = array();
	$objMS->getListOrder(0, "", true);

	foreach($objMS->MSPosition as $key => $arItem){
		if(in_array($arItem["name"], $arOrderID)){
			$msOrderAll["s1"][$arItem["name"]] = array(
				"ORDER_NUMBER" => $arItem["name"],
				"UPDATED" => $arItem["updated"],
				"STATE_META" => $arItem["state"]["meta"]["href"],
				"SUMM" => $arItem["sum"],
			);
		}
	}
	//s2
	$objMS = new MoyskladAPI("s2");
	$objMS->MSPosition = array();
	$objMS->getListOrder(0, "", true);

	foreach($objMS->MSPosition as $key => $arItem){
		if(in_array($arItem["name"], $arOrderID)){
			$msOrderAll["s2"][$arItem["name"]] = array(
				"ORDER_NUMBER" => $arItem["name"],
				"UPDATED" => $arItem["updated"],
				"STATE_META" => $arItem["state"]["meta"]["href"],
				"SUMM" => $arItem["sum"],
			);
		}
	}
	//s3
	$objMS = new MoyskladAPI("s3");
	$objMS->MSPosition = array();
	$objMS->getListOrder(0, "", true);

	foreach($objMS->MSPosition as $key => $arItem){
		if(in_array($arItem["name"], $arOrderID)){
			$msOrderAll["s3"][$arItem["name"]] = array(
				"ORDER_NUMBER" => $arItem["name"],
				"UPDATED" => $arItem["updated"],
				"STATE_META" => $arItem["state"]["meta"]["href"],
				"SUMM" => $arItem["sum"],
			);
		}
	}

	foreach($msOrderAll as $site_id => $arItem){
		$objMS = new MoyskladAPI($site_id);
		foreach($arItem as $key => $ar){
			$status = "unknown";
			//sleep(1);
			usleep(500000);// 0.5 секунды
			if($ar["STATE_META"] && $rs = $objMS->customRequest($ar["STATE_META"])){
				if(!$rs || $rs["errors"]){
					$status = "не получен";
				}elseif($rs["name"]){
					$status = $rs["name"];
				}
			}
			$msOrder[$ar["ORDER_NUMBER"]] = array(
				"ORDER_NUMBER" => $ar["ORDER_NUMBER"],
				"UPDATED" => $ar["UPDATED"],
				"PRICE" => $ar["SUMM"] / 100,
				"STATUS" => trim(preg_replace('/\[.*?\]/', '', $status)),
			);
		}
	}
}
file_put_contents("/home/bitrix/logs/check_orders.txt", "msOrder " . print_r($msOrder, true), FILE_APPEND);
$statusResult = \Bitrix\Sale\Internals\StatusLangTable::getList(array(
    'select' => array('STATUS_ID','NAME'),
));

while($status = $statusResult->fetch()){
	$bxStatus[$status["STATUS_ID"]] = $status["NAME"];
}
$statusCrm = \Bitrix\Main\Config\Option::get("intaro.retailcrm", "pay_statuses_arr");
$statusCrm = unserialize($statusCrm);
foreach($statusCrm as $bx_status => $crm_status){
	$arStatusCRM[$crm_status] = array(
		"BITRIX" => $bx_status,
		"NAME" => $bxStatus[$bx_status],
	);
}

foreach($arOrderCRM as $key => $arItem){
	$diff = strtotime($arItem["statusUpdatedAt"]) - strtotime($bxOrder[$arItem["number"]]["DATE_UPDATE"]);
	$diff = abs($diff);

	if($diff > $limitTime){
		if($bxOrder[$arItem["number"]]){
			$order = $bxOrder[$arItem["number"]];

			if($arItem["totalSumm"] != $order["PRICE"]){
				$arError["BX"][] = $arItem["number"] . " суммы не совпадают. <a href='https://tempusshop.retailcrm.ru/orders/{$arItem["id"]}/edit' target='_blank'>CRM</a> - {$arItem["totalSumm"]}, <a href='https://tempusshop.ru/bitrix/admin/sale_order_view.php?ID={$order["ID"]}' target='_blank'>BX</a> - {$order["PRICE"]}";
			}
			//if(preg_replace('~[^0-9]~', '', $arItem["phone"]) != preg_replace('~[^0-9]~', '', $order["PHONE"]))
			//	$arError["BX"][] = $arItem["number"] . " телефоны не совпадают. CRM - {$arItem["phone"]}, BX - {$order["PHONE"]}";
			if($arStatusCRM[$arItem["status"]]["BITRIX"] != $order["STATUS_ID"])
				$arError["BX"][] = $arItem["number"] . " статус не совпадают. <a href='https://tempusshop.retailcrm.ru/orders/{$arItem["id"]}/edit' target='_blank'>CRM</a> - {$arItem["status"]}, <a href='https://tempusshop.ru/bitrix/admin/sale_order_view.php?ID={$order["ID"]}' target='_blank'>BX</a> - {$order["STATUS_ID"]}";
		}else{
			$arError["BX"][] = $arItem["number"] . " не найден в битриксе";
		}
	}


	// ms
	// $diff = strtotime($arItem["statusUpdatedAt"]) - strtotime($msOrder[$arItem["number"]]["UPDATED"]);
	// $diff = abs($diff);
	$diff_ms = time() - strtotime($msOrder[$arItem["number"]]["UPDATED"]);
	$diff_crm = time() - strtotime($arItem["statusUpdatedAt"]);
	if($diff_ms > $limitTime & $diff_crm > $limitTime){
		if($msOrder[$arItem["number"]]){
			$order = $msOrder[$arItem["number"]];
			if($order["STATUS"] != $arStatusCRM[$arItem["status"]]["NAME"])
				$arError["MS"][] = $arItem["number"] . " статус не совпадают. <a href='https://tempusshop.retailcrm.ru/orders/{$arItem["id"]}/edit' target='_blank'>CRM</a> - {$arItem["status"]}, MS - {$order["STATUS"]}";

			if($arItem["totalSumm"] != $order["PRICE"])
				$arError["MS"][] = $arItem["number"] . " суммы не совпадают. <a href='https://tempusshop.retailcrm.ru/orders/{$arItem["id"]}/edit' target='_blank'>CRM</a> - {$arItem["totalSumm"]}, MS - {$order["PRICE"]}";
		}else{
			//$time_diff = time() - strtotime($arItem["createdAt"]);

			//if($time_diff > 600)
				$arError["MS"][] = $arItem["number"] . " не найден в MS";
		}
	}

}


// выбираем последнюю тысячу из битрикса и шлем в црм
$resBX = $objService->getOrderCache(array("ID" => "DESC"), array(), array("nTopCount" => 1000));
$bxOrder = $arOrderCRM = array();
foreach($resBX as $key => $arItem){
	$bxOrder[$arItem["ORDER_ID"]] = array(
		"ID" => $arItem["ID"],
		"ORDER_NUMBER" => $arItem["ORDER_ID"],
		"STATUS_ID" => $arItem["STATUS_ID"],
		"PHONE" => $arItem["PHONE"],
		"CANCELED" => $arItem["CANCELED"],
		"DATE_UPDATE" => $arItem["DATE_UPDATE"],
		"PRICE" => $arItem["PRICE"],
	);
}

file_put_contents("/home/bitrix/logs/check_orders.txt", "bxOrder2 " . print_r($bxOrder, true), FILE_APPEND);
$cntStep = 100;

$arIDs = array_keys($bxOrder);
$cnt = ceil(count($arIDs) / $cntStep);
for($i = 0; $i < $cnt; $i++){
	$ids = array_slice($arIDs, $i * $cntStep, $cntStep);
	//prent($ids,0,1);

	//file_put_contents("/home/bitrix/check.txt", print_r($ids, true), FILE_APPEND);
	$response = $api->getListOrder($cntStep, 1, array("numbers" => $ids));
	$res = objectToArray($response);

	//file_put_contents("/home/bitrix/check.txt", print_r($res, true), FILE_APPEND);
	foreach($res["response"]["orders"] as $key => $arItem){
		$arOrderCRM[$arItem["number"]] = array(
			"id" => $arItem["id"],
			"number" => $arItem["number"],
			"status" => $arItem["status"],
			"phone" => $arItem["phone"],
			"statusUpdatedAt" => $arItem["statusUpdatedAt"],
			"summ" => $arItem["summ"],
			"totalSumm" => $arItem["totalSumm"],
		);
	}
}
file_put_contents("/home/bitrix/logs/check_orders.txt", "arOrderCRM2 " . print_r($arOrderCRM, true), FILE_APPEND);
//file_put_contents("/home/bitrix/logs/check_orders2.txt", "arOrderCRM2 " . print_r($arOrderCRM, true), FILE_APPEND);
//file_put_contents("/home/bitrix/logs/check_orders2.txt", "bxOrder " . print_r($bxOrder, true), FILE_APPEND);
foreach($bxOrder as $key => $arItem){
	$diff = strtotime($arItem["DATE_UPDATE"]) - strtotime($arOrderCRM[$arItem["ORDER_NUMBER"]]["statusUpdatedAt"]);
	$diff = abs($diff);

	if($arOrderCRM[$arItem["ORDER_NUMBER"]]){
		$diff_bx = time() - strtotime($arItem["DATE_UPDATE"]);
		$diff_crm = time() - strtotime($arOrderCRM[$arItem["ORDER_NUMBER"]]["statusUpdatedAt"]);
		if($diff_bx > $limitTime & $diff_crm > $limitTime){
			$arTimes[$arItem["ID"]] = ['DIFF_BX' => $diff_bx, 'DIFF_CRM' => $diff_crm, 'TIMELIMIT' => $limitTime];
			$order = $arOrderCRM[$arItem["ORDER_NUMBER"]];

			if($arItem["PRICE"] != $order["totalSumm"])
				$arError["CRM"][] = $arItem["ORDER_NUMBER"] . " суммы не совпадают. <a href='https://tempusshop.ru/bitrix/admin/sale_order_view.php?ID={$arItem["ID"]}' target='_blank'>BX</a> - {$arItem["PRICE"]}, <a href='https://tempusshop.retailcrm.ru/orders/{$order["id"]}/edit' target='_blank'>CRM</a> - {$order["totalSumm"]}";
			//if(preg_replace('~[^0-9]~', '', $arItem["PHONE"]) != preg_replace('~[^0-9]~', '', $order["phone"]))
			//	$arError["CRM"][] = $arItem["ORDER_NUMBER"] . " телефоны не совпадают. BX - {$arItem["PHONE"]}, CRM - {$order["phone"]}";
			if($arStatusCRM[$order["status"]]["BITRIX"] != $arItem["STATUS_ID"])
				$arError["CRM"][] = $arItem["ORDER_NUMBER"] . " статус не совпадают. <a href='https://tempusshop.ru/bitrix/admin/sale_order_view.php?ID={$arItem["ID"]}' target='_blank'>BX</a> - {$arItem["STATUS_ID"]}, <a href='https://tempusshop.ru/bitrix/admin/sale_order_view.php?ID={$arItem["ID"]}' target='_blank'>BX</a> - {$arItem["PRICE"]}, <a href='https://tempusshop.retailcrm.ru/orders/{$order["id"]}/edit' target='_blank'>CRM</a> - {$order["status"]}";

		}

	}else{
		if($arItem["CANCELED"] == "N")
			$arError["CRM"][] = $arItem["ORDER_NUMBER"] . " не найден в crm";
	}

}
file_put_contents("/home/bitrix/logs/check_orders2.txt", "arTimes" . print_r($arTimes, true), FILE_APPEND);
unset($arTimes);
// берем выборку 10к заказов и смотрим дубли свойств
$resBX = $objService->getOrderCache(array("ID" => "DESC"), array(), array("nTopCount" => 1000));
$bxOrder = $arOrderCRM = array();
foreach($resBX as $key => $arItem){
	$bxOrder[$arItem["ORDER_ID"]] = array(
		"ID" => $arItem["ID"],
		"ORDER_NUMBER" => $arItem["ORDER_ID"],
		"STATUS_ID" => $arItem["STATUS_ID"],
		"PHONE" => $arItem["PHONE"],
		"CANCELED" => $arItem["CANCELED"],
		"DATE_UPDATE" => $arItem["DATE_UPDATE"],
		"PRICE" => $arItem["PRICE"],
		"MAXYSS_WB_STIKER" => $arItem["MAXYSS_WB_STIKER"],
		"MAXYSS_OP_STICKER" => $arItem["MAXYSS_OP_STICKER"],
		"SBER_ID" => $arItem["SBER_ID"],
		"OZON_NUMBER" => $arItem["OZON_NUMBER"],
		"MAXYSS_WB_NUMBER" => $arItem["MAXYSS_WB_NUMBER"],
		"ONLINER_ORDER_KEY" => $arItem["ONLINER_ORDER_KEY"],
	);
}

foreach($bxOrder as $key => $arItem){

	/*
	смотрим дубли в свойствах заказа
	MAXYSS_WB_STIKER
	MAXYSS_OP_STICKER
	SBER_ID
	OZON_NUMBER
	MAXYSS_WB_NUMBER,
	ONLINER_ORDER_KEY
	*/
	if($arItem["MAXYSS_WB_STIKER"]){
		$arAll["MAXYSS_WB_STIKER"][$arItem["MAXYSS_WB_STIKER"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
	if($arItem["MAXYSS_OP_STICKER"]){
		$arAll["MAXYSS_OP_STICKER"][$arItem["MAXYSS_OP_STICKER"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
	if($arItem["SBER_ID"]){
		$arAll["SBER_ID"][$arItem["SBER_ID"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
	if($arItem["OZON_NUMBER"]){
		$arAll["OZON_NUMBER"][$arItem["OZON_NUMBER"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
	if($arItem["MAXYSS_WB_NUMBER"]){
		$arAll["MAXYSS_WB_NUMBER"][$arItem["MAXYSS_WB_NUMBER"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
	if($arItem["ONLINER_ORDER_KEY"]){
		$arAll["ONLINER_ORDER_KEY"][$arItem["ONLINER_ORDER_KEY"]][] = ["ID" => $arItem["ID"], "ORDER_NUMBER" => $arItem["ORDER_NUMBER"]];
	}
}

foreach($arAll as $key => $ar){
	foreach($ar as $num => $arItem){
		if(count($arItem) > 1){
			$txt = "Дубль " . $key;
			foreach($arItem as $v){
				$txt .= " <a href='https://tempusshop.ru/bitrix/admin/sale_order_view.php?ID={$v["ID"]}' target='_blank'>{$v["ORDER_NUMBER"]}</a>";
			}
			$arError["DOUBLE_PROP_ORDER"][] = $txt;
		}
	}
}


if(!is_array($msOrder)){
	$arError["MS"] = [];
	$arError["MS"][] = "MS данные не получены";
}

$txt = "";
if(count($arError) > 0){
	foreach($arError as $cabinet => $error){
		$txt .= "<hr><p>Ошибки {$cabinet}</p><hr>";
		foreach($error as $k => $er){
			$txt .= "<p>{$er}</p>";
		}
	}

	$arEventFields = array(
		"EMAIL_TO"	=> "sales@tempus.by,f2v1ck@gmail.com,morozenok.a@tempus.by",//
		"MESSAGE"	=> $txt,
		"SUBJECT"	=> "Ошибки в заказах",
	);

	CEvent::Send("IM_NEW_MESSAGE", "s1", $arEventFields, "Y");


	$arLog = array(
		"DATE" => date("Y-m-d H:i:s"),
		"arError" => $arError,
		"msOrder" => $msOrder,
	);
	file_put_contents("/userscripts/log/check_orders.txt", serialize($arLog) . "\r\n", FILE_APPEND | LOCK_EX);

}


//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
