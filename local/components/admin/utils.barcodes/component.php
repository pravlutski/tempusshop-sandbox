<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>
<?
set_time_limit(600);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 600);
global $APPLICATION;

if(!CModule::IncludeModule('panel.manager'))return;

use Bitrix\Main\Loader;

Loader::includeModule('iblock');
if (!class_exists('OrderPrintManager')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/OrderPrintManager.php';
}
if (!class_exists('WildberriesAPI')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/WildberriesAPI.php';
}
include('tools.php');

global $USER;
$arGroups = $USER->GetUserGroupArray();
$objUtils = new CPanelUtils;
/*if (!$USER->IsAdmin() && !in_array(6, $arGroups))
{
    $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
    return ;
}*/

global $DB;


$strSql = "SELECT el.ID as ID, pr.PROPERTY_123 as ARTICLE
	FROM
		b_iblock_element el
	LEFT JOIN
		b_iblock_element_prop_s16 pr
	ON el.ID=pr.IBLOCK_ELEMENT_ID
	WHERE
		el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";

$arResult = array();
//$arResult["SORT_ENABLE"] = true;

$arResult["MARKET_STICKER_ERROR"] = [];

$arResult["SETTINGS"] = unserialize(CProSet::getOption("SETTINGS_UTILS_BARCODE"));
$arResult["TRADING_PRIORITY_LIST"] = [
	'sites_s2', 
	'onliner', '21_vek', 
	'sites', 
	'SITES_NKZ',
	'SITES_NEMIGA',
	'avito_export', 
	'yamarket_marketp_dbs', 
	'YM_780792', 
	'YM_FBS',
	'YM_DBS', 
	'sber', 'yandex', 'wb', 'wbby', 'ozon', 'wb_fbo', 'ozon_fbo', 'avito_retail', 
	'purchase_s1', 'purchase_s2', 'purchase_s1_nkz', 
];

$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()){
	if(strlen($row["ARTICLE"]) > 0){
		$arResult["ARTICLE_BX"][$row["ID"]] = $row["ARTICLE"];
		$arResult["ID_BX"][$row["ARTICLE"]] = $row["ID"];
	}
}

$objUtils = new CPanelUtils;
$objService = new OrderService;
$objProduct = new CPanelProduct;

$arResult["GROUP_RESULT"] = true;

$arResult["COUNT_ITEMS"] = array();
$arResult["ORDER_W_STICKER"] = [];
$arResult["GROUP_ORDER_W_STICKER"] = [];
$arResult["ORDER_SOURCE"] = [];
$arResult["ORDER_MARKET_NUMBERS"] = [];
$arResult["PURCHASE_ITEMS"] = [];

$arResult["PROP_KEY_MARKET"] = false;
$arResult["ERRORS"] = [];
$arIDs = [];

$arParams["date_to"] = date('d.m.Y 14:00');
$arParams["date_to_by"] = date('d.m.Y 14:00', strtotime('-1 day'));
$arResult["ONLY_UNSENT"] = $_REQUEST['only_unsent'] == 1 ?? false;

if(
	($_REQUEST["order_marketplace_submit"] || $_REQUEST["order_export_list"]) && strlen($_REQUEST["date_to"]) > 0 &&
	is_array($_REQUEST['cabinet']) && count($_REQUEST['cabinet']) > 0
){
	
	$arCabinet = [];
	foreach ($_REQUEST['cabinet'] as $cabinet) {
		
		if (!in_array($cabinet, ["WB_WR", "WB_IP", "OZON_IP", "YANDEX", "EXPRESS", "WB_BY", "OZON_BY", "SDEK", "AVITO"])) continue;
		
		if ($cabinet == 'EXPRESS') {
			$arCabinet['YANDEX'] = 'YANDEX';
			$arCabinet['OZON_IP'] = 'OZON_IP';
		} else {
			$arCabinet[$cabinet] = $cabinet;
		}
		
	}
	
	$cabinetExpress = is_array($_REQUEST['cabinet']) && in_array('EXPRESS', $_REQUEST['cabinet']) ?? false;

	$arExpressOrder = [];
	foreach ($arCabinet as $cabinet) {
		$arPreOrder = [];
		
		$arParams["date_to"] = str_replace('/', '.', $_REQUEST["date_to"]);
		$arFilter = array(
			"LID" => "s1",
			"<=DATE_INSERT" => date($DB->DateFormatToPHP(CSite::GetDateFormat("FULL")), strtotime($_REQUEST["date_to"])),//"30.09.2021 11:51:04",
		);

		if ($cabinet == "WB_WR") {
			$arFilter["USER.EMAIL"]	= '796079104009249351@emailwb.ru';
			$arFilter["USER_ID"] = 135989;
			$arFilter["STATUS_ID"] = ["CL"];
			$arFilter["!PROPERTY_VAL_BY_CODE_MAXYSS_WB_NUMBER"] = false;
			
			$arResult["PROP_KEY_MARKET"] = "MAXYSS_WB_NUMBER";
			$arResult["ORDER_SOURCE"][] = "wb";
			$source = "wb";
		} elseif($cabinet == "WB_IP") {
			$arFilter["USER.EMAIL"]	= '111796079104009249351@emailwb.ru';
			$arFilter["USER_ID"] = 161898;
			$arFilter["STATUS_ID"] = ["CL"];
			$arFilter["!PROPERTY_VAL_BY_CODE_MAXYSS_WB_NUMBER"] = false;
			
			$arResult["PROP_KEY_MARKET"] = "MAXYSS_WB_NUMBER";
			$arResult["ORDER_SOURCE"][] = "wb";
			$source = "wb";
		} elseif ($cabinet == "OZON_IP") {
			$arFilter["USER_ID"] = 182118;
			$arFilter["!PROPERTY_VAL_BY_CODE_OZON_NUMBER"] = false;
			//$arFilter["!PROPERTY_VAL_BY_CODE_OZON_ORDER_TYPE"] = 'rFBS';
			$arFilter["STATUS_ID"] = ["SE", "CL"];
			
			$arResult["PROP_KEY_MARKET"] = "OZON_NUMBER";
			$arResult["ORDER_SOURCE"][] = "ozon";
			$source = "ozon";
		} elseif ($cabinet == "YANDEX") {
			$arFilter["USER_ID"] = 81140;
			$arFilter["!PROPERTY_VAL_BY_CODE_ORDER_NUMBER_YA"] = false;
			$arFilter["STATUS_ID"] = ["SE", "CL"];
			
			$arResult["PROP_KEY_MARKET"] = "ORDER_NUMBER_YA";
			$arResult["ORDER_SOURCE"][] = "yandex";
			$source = "yandex";
		} elseif ($cabinet == "WB_BY") {
			$arFilter["USER_ID"] = 191551;
			$arFilter["STATUS_ID"] = ["CL"];
			$arFilter["!PROPERTY_VAL_BY_CODE_MAXYSS_WB_NUMBER"] = false;
			
			$arResult["PROP_KEY_MARKET"] = "MAXYSS_WB_NUMBER";
			$arResult["ORDER_SOURCE"][] = "wb";
			//$source = "wb";
			$source = "wb_by";
			$arFilter['LID'] = 's2';
		} elseif ($cabinet == "SDEK") {
			//$arFilter["USER_ID"] = 191551;
			$arFilter["STATUS_ID"] = ["CL", "SE"];
			$arFilter["CANCELED"] = 'N';
			//$arFilter["SOURCE"] = 4;
			//$arFilter["ID"] = 740006;
			//$arFilter["!PROPERTY_VAL_BY_CODE_IPOLSDEK_CNTDTARIF"] = false;
			
			
			// для сдэк получаем по дибильному. 3 запроса
			$arFilter["DELIVERY_ID"] = 9;
			$arPreOrder = $objService->getOrder(array(), $arFilter);
			
			$arFilter["DELIVERY_ID"] = 10;
			foreach ($objService->getOrder(array(), $arFilter) as $order) {
				$arPreOrder[] = $order;
			}

			$arFilter["DELIVERY_ID"] = 11;
			foreach ($objService->getOrder(array(), $arFilter) as $order) {
				$arPreOrder[] = $order;
			}
			
			//$arFilter['LID'] = 's2';
			unset($arFilter['LID']);
			
			$arResult["PROP_KEY_MARKET"] = "TRACKING_NUMBER";
			$arResult["ORDER_SOURCE"][] = "sdek";
			$source = "sdek";
		} elseif ($cabinet == "OZON_BY") {
			$arFilter["USER_ID"] = 193181;
			$arFilter["!PROPERTY_VAL_BY_CODE_OZON_NUMBER"] = false;
			$arFilter["STATUS_ID"] = ["SE", "CL"];
			
			$arResult["PROP_KEY_MARKET"] = "OZON_NUMBER";
			$arResult["ORDER_SOURCE"][] = "ozon";
			$source = "ozon_by";
			$arFilter['LID'] = 's2';
		} elseif ($cabinet == "AVITO") {
			$arFilter["USER_ID"] = 197524;
			$arFilter["!PROPERTY_VAL_BY_CODE_AVITO_ORDER_NUMBER"] = false;
			$arFilter["STATUS_ID"] = ["SE", "CL"];
			
			$arResult["PROP_KEY_MARKET"] = "AVITO_ORDER_NUMBER";
			$arResult["ORDER_SOURCE"][] = "avito";
			$source = "avito";
			$arFilter['LID'] = 's1';
		} 

		/* elseif ($cabinet == "EXPRESS") {
			$arFilter["USER_ID"] = 81140;
			$arFilter["!PROPERTY_VAL_BY_CODE_ORDER_NUMBER_YA"] = false;
			$arFilter["STATUS_ID"] = ["SE", "CL"];
			
			$arResult["PROP_KEY_MARKET"] = "ORDER_NUMBER_YA";
			$arResult["ORDER_SOURCE"][] = "yandex";
			$source = "yandex";
		}*/

		$arResult["ORDER_SOURCE"] = array_unique($arResult["ORDER_SOURCE"]);
		
		if ($cabinet == "SDEK") {
			$arOrder = $arPreOrder;
		} else {
			$arOrder = $objService->getOrder(array(), $arFilter);
		}
		
		$arOrder = array_reverse( $arOrder );

		$currentDateTime = new DateTime();
		
		$arderIDs = array_column($arOrder, 'ID');
		$printHistory = OrderPrintManager::getPrintHistory($arderIDs);
		$arPrint = [];
		foreach ($printHistory as $arItem) {
			if (!$arItem["ORDER_ID"] || !$arItem["PRODUCT_ID"] || !$arItem["NUMBER_ID"]) continue;
			$key = "{$arItem["ORDER_ID"]}-{$arItem["PRODUCT_ID"]}-{$arItem["NUMBER_ID"]}";
			$arPrint[$key][] = $arItem;
		}

		foreach($arOrder as $key => $order){

			$insertDateTime = new DateTime($order["DATE_INSERT"]);
			$insertDateTime->setTime(23, 59, 59);
			$interval = $insertDateTime->diff($currentDateTime);
			$insertHour = $interval->h + ($interval->days * 24);
			$insertDate = $insertDateTime->format('d.m');

			$is_group = false;
			if (is_array($order["BASKET"]) && count($order["BASKET"]) > 1) $is_group = true;
			foreach($order["BASKET"] as $k => $arItem){

				$arIDs[$arItem["PRODUCT_ID"]] = $arItem["PRODUCT_ID"];
				if ($arItem["QUANTITY"] > 1) $is_group = true;
				for($i = 0; $i < $arItem["QUANTITY"]; $i++){
					
					$number_id = $i + 1;
									
					$key = "{$order["ID"]}-{$arItem["PRODUCT_ID"]}-{$number_id}";
					if ($arPrint[$key]) {
						$is_print = true;
					} else {
						$is_print = false;
					}
				
					$arResult["ORDER_ITEMS_MARKET"][] = array(
						"ID" => $order["ID"],
						"ORDER_NUMBER_ID" => $order["ORDER_ID"],
						"ORDER_MARKET_NUMBER" => $order[$arResult["PROP_KEY_MARKET"]],
						"ORDER_COMMENTS" => $order["COMMENTS"],
						"PRODUCT_ID" => $arItem["PRODUCT_ID"],
						"CATALOG_XML_ID" => $arItem["CATALOG_XML_ID"],
						"MAXYSS_OP_STICKER" => $order["MAXYSS_OP_STICKER"],
						"OZON_NUMBER" => $order["OZON_NUMBER"],
						"ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"SORT_ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"INSERT_DATE" => $insertDate,
						"INSERT_HOUR" => $insertHour,
						"SOURCE" => $source,
						"STICKER" => (in_array($source, ['avito']) ? true : false),
						//"STICKER_PRINT" => ($order["STICKER_PRINT"] == 'Y' ? true : false),

						"STICKER_PRINT" => $is_print,
						"STICKER_PRINT_HISTORY" => $arPrint[$key] ?? [],
						"IS_GROUP" => $is_group,
						"NUMBER_ID" => $number_id,
						"CABINET" => $cabinet,
					);
				}
			}

			if (in_array($order['MAXYSS_WB_CABINET'], ['WR', 'TL', 'WT'])) {
				if ($order['MAXYSS_WB_CABINET'] == 'TL') {
					$k = 'WB_IP';
				} elseif ($order['MAXYSS_WB_CABINET'] == 'WT') {
					$k = 'WB_BY';
				} else {
					$k = 'WB_WR';
				}
				$arResult["ORDER_MARKET_NUMBERS"][$k][] = $order[$arResult["PROP_KEY_MARKET"]];
			} else {
				$arResult["ORDER_MARKET_NUMBERS"][$source][$order["ID"]] = $order[$arResult["PROP_KEY_MARKET"]];
			}
			
			if ($cabinet == 'OZON_IP' && $order["OZON_ORDER_TYPE"] == 'rFBS') {
				$arExpressOrder[] = $order["ID"];
			}
			//$arResult["ORDER_MARKET_NUMBERS"][$order['MAXYSS_WB_CABINET']][] = $order[$arResult["PROP_KEY_MARKET"]];
		}

		if(
			is_array($arResult["ORDER_MARKET_NUMBERS"][$cabinet]) && 
			count($arResult["ORDER_MARKET_NUMBERS"][$cabinet]) > 0 && 
			in_array($cabinet, ['WB_IP', 'WB_WR', 'WB_BY'])
		){
			//отсылаем в WB

			
			// WB - перед запросом этикеток переводить заказ в статусconfirm с помощью метода 
			$arNumber = [];
			foreach ($arResult["ORDER_MARKET_NUMBERS"][$cabinet] as $order_market_number) {
				if (!file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/wb/{$order_market_number}.svg")) {
					$arNumber[] = intval($order_market_number);
				}
			}

			if (count($arNumber) > 0) {
				// ищем поставку
				if ($cabinet == 'WB_WR') 
					$cabinetWB = 'WR';
				elseif ($cabinet == 'WB_BY')
					$cabinetWB = 'WB_BY';
				else
					$cabinetWB = 'TL';

				$wb = new WildberriesAPI($cabinetWB);
				
				$supplyList = $wb->getSuppliesActive();  

				$supplyId = false;
				if (is_array($supplyList) && count($supplyList) > 0) {

					usort($supplyList, function($a, $b) {
						return strtotime($a['createdAt']) <=> strtotime($b['createdAt']);
					});

					$supplyId = $supplyList[0]['id'];
				}
				//WB-GI-180783756*/
				//prent($supplyId);

				if (!$supplyId) {
					$name = date("d.m.Y");
					$supplie = $wb->createSupplie($name);
					
					if ($supplie['id']) {
						$supplyId = $supplie['id'];
					}
				}
				if ($supplyId) {
					$batchSize = 75;
					$total = count($arNumber);

					for ($i = 0; $i < $total; $i += $batchSize) {
						$chunk = array_slice($arNumber, $i, $batchSize);
						
						$res = $wb->orderToSupplie($supplyId, $chunk);
						
						if ($res['status'] != 204) {
							file_put_contents("/home/bitrix/logs/utils/utils.barcodes_new2.txt", print_r(
								[date('Y-m-d H:i:s'), $USER->getID(), $supplyId, $chunk, $res], true), 8
							);
							//foreach ($res['ERROR'] as $e) {
							//	$arResult["ERRORS"][] = $e;
							//}
						}
					}
					/*
					foreach ($arNumber as $orderId) {
						//prent($orderId);
						global $USER;
						if ($USER->getID() == 12677) {
							//prent($supplyList);
							$res = $wb->orderToSupplie($supplyId, $orderId);
						}

						if ($res['status'] != 204) {
							file_put_contents("/home/bitrix/logs/utils/utils.barcodes_new2.txt", print_r([$supplyId, $orderId, $res], true), 8);
							//foreach ($res['ERROR'] as $e) {
							//	$arResult["ERRORS"][] = $e;
							//}
						}
					}*/
				} else {
					file_put_contents("/home/bitrix/logs/utils/utils.barcodes_new2.txt", print_r([date('Y-m-d H:i:s'), 'нет активной поставки'], true), 8);
				}
			}
			
			$cabinetType = str_replace("WB_", "", $cabinet);
			$arStickerOrder = [];
			$arStickerWB = getStickerWB($arResult["ORDER_MARKET_NUMBERS"][$cabinet], $cabinetType);
			foreach($arResult["ORDER_ITEMS_MARKET"] as $k => &$arItem){
				$order_market_number = $arItem["ORDER_MARKET_NUMBER"];
				if($arStickerWB[$order_market_number]){
					$arItem["STICKER"] = $arStickerWB[$order_market_number];
					$arStickerOrder[] = $arStickerWB[$order_market_number];

					//пишим в свойство если какого то хуя модуль не записал
					if((!$arItem["MAXYSS_OP_STICKER"] || ($arItem["MAXYSS_OP_STICKER"] != $arItem["STICKER"]["STICKER_ENCODING"])) && $arItem["STICKER"]["STICKER_ENCODING"]){
						//prent($arItem,0,1);//MAXYSS_WB_STIKER
						AddOrderProperty(62, $arItem["STICKER"]["STICKER_ENCODING"], $arItem["ID"]);
					}

				}
			}
			unset($arItem);

			$arStickerOrder = sort_nested_arrays($arStickerOrder, array('STICKER_PART_B' => 'asc'));

			foreach($arStickerOrder as $key => $arItem){
				$arResult["ORDER_W_STICKER"][] = $arItem["ORDER_ID_WB"];
				$arResult["COUNT_ITEMS"][$arItem["STICKER_PART_B"]] += 1;
			}
			//prent($arStickerWB);
			if (is_array($arStickerWB["ERRORS"]) && count($arStickerWB["ERRORS"]) > 0) {
				foreach ($arStickerWB["ERRORS"] as $e) {
					$arResult["ERRORS"][] = $e; 
				}
			}
		}
		
		if(
			is_array($arResult["ORDER_MARKET_NUMBERS"]["ozon"]) && 
			count($arResult["ORDER_MARKET_NUMBERS"]["ozon"]) > 0 && 
			in_array($cabinet, ["OZON_IP"])
		) {
			$cabinetType = str_replace("OZON_", "", $cabinet);
			
			$arNumber = [];
			foreach ($arResult["ORDER_MARKET_NUMBERS"]["ozon"] as $orderId => $order_market_number) {
				if (!$cabinetExpress && in_array($orderId, $arExpressOrder)) continue;
				if (!file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_market_number}.pdf")) {
					$arNumber[] = $order_market_number;
				}
			}
			
			//prent($arNumber);
			// OZON - Перед запросом этикеток переводить заказ в статус awaiting_deliver с помощью метода  https://docs.ozon.ru/api/seller/#operation/PostingAPI_ShipFbsPostingV4
			$skipNumbers = [];
			$fileSkipStatus = '/var/www/bitrix_logs/ozon/tmp/skipOrders.txt';
			if (file_exists($fileSkipStatus)) {
				$skipNumbers = explode("\r\n", file_get_contents($fileSkipStatus));

				$fileTime = time() - shell_exec("stat -c %W $fileSkipStatus");
				if ($fileTime > 86400) {
					unlink($fileSkipStatus);
				}
			}
			
			if (count($arNumber) > 0) {
				foreach ($arNumber as $posting_number) {
					if (in_array($posting_number, $skipNumbers)) continue;
					usleep(200);
					$res = setStatusOzon($posting_number, "IP");

					if ($res['ERROR']) {
						foreach ($res['ERROR'] as $e) {
							$arResult["ERRORS"][] = $e;
							if (strpos($e, 'POSTING_ALREADY_SHIPPED') !== false) {
								file_put_contents($fileSkipStatus, $posting_number . "\r\n", 8);
							}
						}
					} else {
						file_put_contents($fileSkipStatus, $posting_number . "\r\n", 8);
					}
				}
			}
			
			if (count($arNumber) > 0) {
				// сохраняем номера в файл. потом его отправляем на создание
				$filename = time() . ".txt";
				file_put_contents("/var/www/bitrix_logs/ozon/tmp/{$filename}", json_encode($arNumber));
				
				exec("/usr/bin/php /var/www/bitrix/data/www/tempusshop.ru/local/components/admin/utils.barcodes/ozon_sticker.php filename={$filename}", $output);
				
				$resultSticker = json_decode($output[0], true);
				//prent($filename);
				//prent($resultSticker);
				if (is_array($resultSticker["errors"]) && count($resultSticker["errors"])) {
					$arResult["ERRORS"][] = '---- ошибки запроса стикеров ---';
					foreach ($resultSticker["errors"] as $e) {
						$arResult["ERRORS"][] = $e; 
					}
				}
				//prent($resultSticker);
				//prent($arResult["ERRORS"]);
				/*$arResult["MARKET_STICKER_ERROR"] = [
					"error" => is_array($resultSticker["error"]) ? count($resultSticker["error"]) : 'N/A',
					//"no_sticker" => $resultSticker["no_sticker"] >= 0 ? $resultSticker["no_sticker"] : 'N/A',
					"no_sticker" => is_array($resultSticker["no_sticker"]) ? count($resultSticker["no_sticker"]) : 'N/A',
					"not_defined" => is_array($resultSticker["not_defined"]) ? count($resultSticker["not_defined"]) : 'N/A',
				];
				
				if (is_array($resultSticker["error"]) && count($resultSticker["error"])) {
					foreach ($resultSticker["error"] as $e) {
						$arResult["ERRORS"][] = $e;
					}
				}
				
				if (is_array($resultSticker["no_sticker"]) && count($resultSticker["no_sticker"])) {
					foreach ($resultSticker["no_sticker"] as $e) {
						$arResult["ERRORS"][] = $e;
					}
				}
				
				if (is_array($resultSticker["not_defined"]) && count($resultSticker["not_defined"])) {
					foreach ($resultSticker["not_defined"] as $e) {
						$arResult["ERRORS"][] = $e; 
					}
				}
				*/

			}
				
			foreach ($arResult["ORDER_ITEMS_MARKET"] as $k => &$arItem) {
				$order_market_number = $arItem["ORDER_MARKET_NUMBER"];
				if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_market_number}.pdf")) {
					$arItem["STICKER"] = $order_market_number;
				}
			}
			unset($arItem);
		}
		
		if(
			is_array($arResult["ORDER_MARKET_NUMBERS"]["yandex"]) && 
			count($arResult["ORDER_MARKET_NUMBERS"]["yandex"]) > 0 && 
			in_array($cabinet, ["YANDEX"])
		) {

			$arNumber = [];
			$arIds = [];
			foreach ($arResult["ORDER_MARKET_NUMBERS"]["yandex"] as $orderId => $order_market_number) {
				if (!file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/stickers/yandex/{$order_market_number}.pdf")) {
					$arNumber[] = $order_market_number;
				}
				
				$arIds[] = $orderId;
			}
			
			//$strSql = "SELECT * FROM b_sale_tp_order WHERE EXTERNAL_ORDER_ID IN ('".implode("','", $arNumber)."') AND TRADING_PLATFORM_ID = '4'";
			$strSql = "SELECT * FROM b_sale_tp_order WHERE ORDER_ID IN ('".implode("','", $arIds)."') AND TRADING_PLATFORM_ID = '4'";

			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			$arSetting = [];
			while ($row = $results->Fetch()) {

				$setting = unserialize($row['PARAMS']);
				if ($setting['SETUP_ID'] && $setting['CAMPAIGN_ID']) {
					$arSetting[$setting['SETUP_ID']][] = [
						'ORDER_ID' => $row['ORDER_ID'],
						'EXTERNAL_ORDER_ID' => $row['EXTERNAL_ORDER_ID'],
						'CAMPAIGN_ID' => $setting['CAMPAIGN_ID'],
						'SETUP_ID' => $setting['SETUP_ID'],
					];
					if ($setting['CAMPAIGN_ID'] == 148300505) { // nip заказы
						$arExpressOrder[] = $row['ORDER_ID'];
					}
				}
				
			}  
			
			if ($arSetting) {
				// сохраняем в файл. потом его отправляем на создание
				$filename = time() . ".txt";
				file_put_contents("/var/www/bitrix_logs/yandex/{$filename}", json_encode($arSetting)); 

				exec("/usr/bin/php /var/www/bitrix/data/www/tempusshop.ru/local/components/admin/utils.barcodes/yandex_sticker.php filename={$filename}", $output);

				$resultSticker = json_decode($output[0], true);
				
				if (is_array($resultSticker["error"]) && count($resultSticker["error"])) {
					foreach ($resultSticker["error"] as $e) {
						$arResult["ERRORS"][] = $e;
					}
				}
				
				if (is_array($resultSticker["no_sticker"]) && count($resultSticker["no_sticker"])) {
					foreach ($resultSticker["no_sticker"] as $e) {
						$arResult["ERRORS"][] = $e;
					}
				}
				
				if (is_array($resultSticker["not_defined"]) && count($resultSticker["not_defined"])) {
					foreach ($resultSticker["not_defined"] as $e) {
						$arResult["ERRORS"][] = $e; 
					}
				}

			}
				
			foreach ($arResult["ORDER_ITEMS_MARKET"] as $k => &$arItem) {
				$order_market_number = $arItem["ORDER_MARKET_NUMBER"];
				if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/stickers/yandex/{$order_market_number}.pdf")) {
					$arItem["STICKER"] = $order_market_number;
				}
			}
			unset($arItem);
		}
		
		// смотрим сдэк
		if(
			is_array($arResult["ORDER_MARKET_NUMBERS"]["sdek"]) && 
			count($arResult["ORDER_MARKET_NUMBERS"]["sdek"]) > 0 && 
			in_array($cabinet, ["SDEK"])
		) {

			$arNumber = [];
			foreach ($arResult["ORDER_MARKET_NUMBERS"]["sdek"] as $orderId => $order_market_number) {
				if ($order_market_number && !file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/stickers/sdek/{$order_market_number}.pdf")) {
					$arNumber[] = $order_market_number;
				}
				
				if (!$order_market_number) {
					$arResult["ERRORS"][] = $orderId . ' - Трек-код не заполнен';
				}
			}

			// сохраняем номера в файл. потом его отправляем на создание
			if (count($arNumber) > 0) {
				$arNumber = array_unique($arNumber);
				$filename = time() . ".txt";
				file_put_contents("/var/www/bitrix_logs/sdek/{$filename}", json_encode($arNumber));
				//prent($filename);
				exec("/usr/bin/php /var/www/bitrix/data/www/tempusshop.ru/local/components/admin/utils.barcodes/sdek_sticker.php filename={$filename}", $output);
				//prent($output);
				$resultSticker = json_decode($output[0], true);
				$arResult["MARKET_STICKER_ERROR"] = [
					"error" => is_array($resultSticker["error"]) ? count($resultSticker["error"]) : 'N/A',
				];
				
				if (is_array($resultSticker["error"]) && count($resultSticker["error"])) {
					foreach ($resultSticker["error"] as $e) {
						$arResult["ERRORS"][] = $e;
					}
				}
				

			}
			
			foreach ($arResult["ORDER_ITEMS_MARKET"] as $k => &$arItem) {
				$order_market_number = $arItem["ORDER_MARKET_NUMBER"];
				if ($arItem['SOURCE'] == 'sdek' && file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/stickers/sdek/{$order_market_number}.pdf")) {
					$arItem["STICKER"] = $order_market_number;
				}
			}
			unset($arItem);
		}
		
	}
	//prent($cabinetExpress);
	//prent($arExpressOrder);
	//prent($arResult["ORDER_ITEMS_MARKET"]);
	//if (count($arExpressOrder) > 0) {
		$ar = [];
		foreach ($arResult["ORDER_ITEMS_MARKET"] as $k => $v) {
			if (!$cabinetExpress) { // если нет express то очищем от express
				if (!in_array($v['ID'], $arExpressOrder)) {
					$ar[] = $v;
				}
			} else { // если есть express оставляем их и смотрим дальше что в списке
				if (in_array($v['ID'], $arExpressOrder)) {
					$ar[] = $v;
				} elseif (in_array($v['CABINET'], $_REQUEST['cabinet'])) {
					$ar[] = $v;
				}
			}

		}
		$arResult["ORDER_ITEMS_MARKET"] = $ar;
	//} elseif ($cabinetExpress) {

	//prent($arResult["ORDER_ITEMS_MARKET"]);
	//}
	//prent($arResult["ORDER_ITEMS_MARKET"]); 
	/*if (is_array($arResult["ORDER_ITEMS_MARKET"]) && count($arResult["ORDER_ITEMS_MARKET"]) > 0) {
		$arOrderIDs = array_column($arResult["ORDER_ITEMS_MARKET"], 'ID');
		
		$arHistory = OrderPrintManager::getPrintHistory($arOrderIDs);
		foreach ($arHistory as $arItem) {
			$arResult["ORDER_PRINT_HISTORY"][$arItem["ORDER_ID"]][] = [
				"TYPE_SCAN" => $arItem["TYPE_SCAN"],
				"TIMESTAMP" => $arItem["TIMESTAMP"],
			];
		}
	}*/

}elseif($_REQUEST["bel_purchase_list"]){
	$arOrderID = [];

	$objSupplier = new CPanelSupplier;
	$arListSupp = [];
	foreach($objSupplier->getList() as $k => $arItem){
		$settings = json_decode($arItem["settings"], true);

		if($settings["currency_list"] && $settings["currency_list"] == "RUB"){
			$arListSupp[] = $arItem["id"];
		}
	}
	$add_where = [];
	if(count($arListSupp) > 0)
		$add_where[] = "supp_id IN ('".implode("','",$arListSupp)."')";

	$add_where[] = "active = 'Y'";
	$add_where[] = "site_id IN ('s2', 's3')";
	$add_where[] = "status <> 'D'";

	if($add_where && count($add_where) > 0){
		$add_where = implode(" AND ",$add_where);
	}


	$strSql = "SELECT * FROM ci_purchase WHERE " . $add_where;
	//prent($strSql);
	//$strSql = "SELECT * FROM ci_purchase WHERE active = 'Y' AND site_id IN ('s2', 's3') AND status <> 'D'";// AND site_id = '".$psFilter["website"]."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["PURCHASE_ITEMS"][] = array(
			"SITE_ID" => $row["site_id"],
			"MODEL" => $row["model"],
			"ORDER_ID" => ($row["order_id"] > 0 ? $row["order_id"] : ""),
			"ORDER_COMMENTS" => "",
			"ORDER_NUMBER_ID" => "",
			"PRODUCT_ID" => $row["product_id"],
			"PRICE" => $row["price"],
			"SUPPLIER_ID" => $row["supp_id"],
			"ARTICLE" => $arResult["ARTICLE_BX"][$row["product_id"]],
			"SORT_ARTICLE" => $arResult["ARTICLE_BX"][$row["product_id"]],
		);
		//$arArticleBX[] = $arResult["ARTICLE_BX"][$row["product_id"]]; 
		$arIDs[$row["product_id"]] = $row["product_id"];
		if($row["order_id"] > 0){
			$arOrderID[] = $row["order_id"];
		}

	}

	if(count($arOrderID) > 0){
		$arFilter = array(
			//"LID" => $website,
			//"STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CR"),
			//"!CANCELED" => "Y",
			"ID" => $arOrderID
		);
		$order = $objService->getOrder(array(), $arFilter);
		$order = array_reverse( $order );
		foreach($order as $arItem){
			$arOrder[$arItem["ID"]] = [
				"ID" => $arItem["ID"],
				"ORDER_NUMBER_ID" => $arItem["ORDER_ID"],
				"COMMENTS" => $arItem["COMMENTS"],
			];
		}
	}

	//$arResult["SORT_ENABLE"] = false;
}

$objService->getPropOrderFlg = false;


if ($_REQUEST["article_all"]) {
	$article_all = explode("\r\n", $_REQUEST["article_all"]);
	$article_all = array_diff_($article_all, array(''));

	$arArticle = [];
	$arOrderID = [];
	$arOrderYA = [];
	$arOrderOzon = [];

	$arOrderFind = [];
	$arOrderFindOzon = [];
	$arOrderFindYA = [];
	// var_dump( $_REQUEST );

	foreach($article_all as $k => $article){
		if(substr_count($article, '-') == 2){
			$arOrderOzon[] = trim($article);
		}else{
			$arOrderID[] = trim($article);
		}
		$arOrderAll[] = trim($article);
	}

	$arOrderID = array_diff_($arOrderID, array(''));
	$arOrderOzon = array_diff_($arOrderOzon, array(''));
	$arOrderAll = array_diff_($arOrderAll, array(''));
	$arOrderYA = array_diff_($arOrderYA, array(''));

	$arResult["COUNT_ITEMS"] = array();
	if(count_($arOrderID) > 0){
		$arFilter = array(
			//"LID" => $website,
			//"STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CR"),
			//"!CANCELED" => "Y",
			"ACCOUNT_NUMBER" => $arOrderID
		);

		$arOrder = $objService->getOrder(array(), $arFilter);
		$arOrder = array_reverse( $arOrder );
		
		$currentDateTime = new DateTime();

		foreach($arOrder as $key => $order){
			
			$insertDateTime = new DateTime($order["DATE_INSERT"]);
			$insertDateTime->setTime(23, 59, 59);
			$interval = $insertDateTime->diff($currentDateTime);
			$insertHour = $interval->h + ($interval->days * 24);
			$insertDate = $insertDateTime->format('d.m');

			$arOrderFind[] = $order["ORDER_ID"];

			foreach($order["BASKET"] as $k => $arItem){
				$arIDs[$arItem["PRODUCT_ID"]] = $arItem["PRODUCT_ID"];
				for($i = 0; $i < $arItem["QUANTITY"]; $i++){
					$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = array(
						"ID" => $order["ID"],
						"LID" => $order["LID"],
						"ORDER_NUMBER_ID" => $order["ORDER_ID"],
						"ORDER_COMMENTS" => $order["COMMENTS"],
						"PRODUCT_ID" => $arItem["PRODUCT_ID"],
						"ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"SORT_ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"ORDER_QUANTITY" => $arItem["QUANTITY"], 
						"INSERT_DATE" => $insertDate,
						"INSERT_HOUR" => $insertHour,
					);
					$arSort[$order["ORDER_ID"]][] = $arItem["PRODUCT_ID"];
					//$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = $order["ORDER_ID"];
				}

			}
			if (is_array($order["BASKET"])) {
				$arResult["COUNT_ITEMS"][$order["ORDER_ID"]] = count($order["BASKET"]);
			} else {
				print_r('ЗАКАЗ '.$order["ORDER_ID"].' НЕ ОБРАБОТАН ПУСТАЯ КОРЗИНА<br>');
			}
		}

	}

	// заказы озона
	if( count($arOrderOzon) > 0) {
		$arFilter = array(
			//"LID" => $website,
			//"STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CR"),
			//"!CANCELED" => "Y",
			"PROPERTY_VAL_BY_CODE_OZON_NUMBER" => $arOrderOzon
		);
		$objService->getPropOrderFlg = true;
		$arOrder = $objService->getOrder(array(), $arFilter);
		$arOrder = array_reverse( $arOrder );
		$currentDateTime = new DateTime();
		
		foreach($arOrder as $key => $order){
		
			$insertDateTime = new DateTime($order["DATE_INSERT"]);
			$insertDateTime->setTime(23, 59, 59);
			$interval = $insertDateTime->diff($currentDateTime);
			$insertHour = $interval->h + ($interval->days * 24);
			$insertDate = $insertDateTime->format('d.m');
		
			$arOrderFindOzon[] = $order["OZON_NUMBER"];
			foreach($order["BASKET"] as $k => $arItem){
				$arIDs[$arItem["PRODUCT_ID"]] = $arItem["PRODUCT_ID"];
				for($i = 0; $i < $arItem["QUANTITY"]; $i++){
					$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = array(
						"ID" => $order["ID"],
						"LID" => $order["LID"],
						"SOURCE" => "OZ",
 						"ORDER_NUMBER_ID" => $order["ORDER_ID"],
						"ORDER_COMMENTS" => $order["COMMENTS"],
						"PRODUCT_ID" => $arItem["PRODUCT_ID"],
						"ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"SORT_ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"STICKER" => $order["OZON_NUMBER"],
						"ORDER_QUANTITY" => $arItem["QUANTITY"], 
						"INSERT_DATE" => $insertDate,
						"INSERT_HOUR" => $insertHour,
					);
					$arSort[$order["OZON_NUMBER"]][] = $arItem["PRODUCT_ID"];
					//$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = $order["ORDER_ID"];
				}

			}
			if (is_array($order["BASKET"])) {
				$arResult["COUNT_ITEMS"][$order["ORDER_ID"]] = count($order["BASKET"]);
			} else {
				print_r('ЗАКАЗ '.$order["ORDER_ID"].' НЕ ОБРАБОТАН ПУСТАЯ КОРЗИНА<br>');
			}
			// $arResult["COUNT_ITEMS"][$order["ORDER_ID"]] = count($order["BASKET"]);
		}

	}

	// заказы яндекса
	if( count($arOrderYA) > 0) {
		$arFilter = array(
			//"LID" => $website,
			//"STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CR"),
			//"!CANCELED" => "Y",
			"PROPERTY_VAL_BY_CODE_ORDER_NUMBER_YA" => $arOrderYA
		);
		// var_dump($arFilter);
		$objService->getPropOrderFlg = true;
		$arOrder = $objService->getOrder(array(), $arFilter);
		$arOrder = array_reverse( $arOrder );
		foreach($arOrder as $key => $order){
			$arOrderFindYA[] = $order['ORDER_NUMBER_YA'];
			foreach($order["BASKET"] as $k => $arItem){
				$arIDs[$arItem["PRODUCT_ID"]] = $arItem["PRODUCT_ID"];
				for($i = 0; $i < $arItem["QUANTITY"]; $i++){
					$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = array(
						"ID" => $order["ID"],
						"LID" => $order["LID"],
						"ORDER_NUMBER_ID" => $order["ORDER_ID"],
						"ORDER_COMMENTS" => $order["COMMENTS"],
						"PRODUCT_ID" => $arItem["PRODUCT_ID"],
						"ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"SORT_ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
						"STICKER" => $order['ORDER_NUMBER_YA'],
					);
					$arSort[$order['ORDER_NUMBER_YA']][] = $arItem["PRODUCT_ID"];
					//$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = $order["ORDER_ID"];
				}

			}
			$arResult["COUNT_ITEMS"][$order["ORDER_ID"]] = count($order["BASKET"]);
		}

	}

	foreach($arOrderID as $order){
		if(!in_array($order, $arOrderFind)){
			$arResult["ERRORS"][] = "Заказ - " . $order . " не найден";
		}
	}

	foreach($arOrderOzon as $order){
		if(!in_array($order, $arOrderFindOzon)){
			$arResult["ERRORS"][] = "Заказ ozon - " . $order . " не найден";
		}
	}

	foreach($arOrderYA as $order){
		if(!in_array($order, $arOrderFindYA)){
			$arResult["ERRORS"][] = "Заказ Yandex - " . $order . " не найден";
		}
	}
}

if ($_REQUEST['fbo_orders']) {
	//$fbo_order = trim($_REQUEST['fbo_order']);
	$fbo_orders = explode("\r\n", $_REQUEST["fbo_orders"]);
	$fbo_orders = is_array($fbo_orders) ? array_diff($fbo_orders, array('')) : [];
	
	$cabinet = trim($_REQUEST['cabinet']);
	
	$arOrder = [];
	if (count($fbo_orders) > 0) {
		$arFilter = array(
			//"LID" => $website,
			//"STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CR"),
			//"!CANCELED" => "Y",
			"ACCOUNT_NUMBER" => $fbo_orders
		);
		$objService->getPropOrderFlg = true;
		$res = $objService->getOrder(array(), $arFilter);
		
		foreach ($res as $arItem) {
			$arOrder[$arItem['ID']] = $arItem;
		}
	}

	$order = false;
	if (is_array($arOrder) && count($arOrder) > 0) {
		$orderIDs = array_keys($arOrder);
		
		$strSql = "SELECT * FROM b_sale_tp_order WHERE ORDER_ID IN ('".implode("','", $orderIDs)."') AND TRADING_PLATFORM_ID IN ('16', '17', '18')";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()) {
			if ($row['TRADING_PLATFORM_ID'] == 16) {
				$arOrder[$row['ORDER_ID']]['TRADING_NAME'] = 'ozon_fbo';
			} else if ($row['TRADING_PLATFORM_ID'] == 17){
				$arOrder[$row['ORDER_ID']]['TRADING_NAME'] = 'wb_fbo';
			} else if ($row['TRADING_PLATFORM_ID'] == 18){
				$arOrder[$row['ORDER_ID']]['TRADING_NAME'] = '21_vek';
			}
		}
//		else {
		//	$arResult["ERRORS"][] = "Заказ {$fbo_order} не является FBO заказом";
		//	unset($order);
		//}
		
	}

	$arderIDs = array_column($arOrder, 'ID');
	$printHistory = OrderPrintManager::getPrintHistory($arderIDs);
	$arPrint = [];
	foreach ($printHistory as $arItem) {
		if (!$arItem["ORDER_ID"] || !$arItem["PRODUCT_ID"] || !$arItem["NUMBER_ID"]) continue;
		$key = "{$arItem["ORDER_ID"]}-{$arItem["PRODUCT_ID"]}-{$arItem["NUMBER_ID"]}";
		$arPrint[$key][] = $arItem;
	}

	foreach ($arOrder as $order) {
		if (
			!$cabinet || 
			!in_array($cabinet, ['WB_WR', 'WB_IP', 'OZON_IP', '21_VEK']) ||
			($cabinet == 'WB_WR' && $order['TRADING_NAME'] != 'wb_fbo') || 
			($cabinet == 'WB_IP' && $order['TRADING_NAME'] != 'wb_fbo') || 
			($cabinet == 'OZON_IP' && $order['TRADING_NAME'] != 'ozon_fbo') || 
			($cabinet == '21_VEK' && $order['TRADING_NAME'] != '21_vek')
		) {
			$arResult["ERRORS"][] = "Заказ {$order['ORDER_ID']} не соответсвует кабинету";
			continue;
		}
		
		/*$printHistory = OrderPrintManager::getPrintHistory($order["ID"]);
		$arPrint = [];
		foreach ($printHistory as $arItem) {
			if (!$arItem["PRODUCT_ID"] || !$arItem["NUMBER_ID"]) continue;
			$arPrint[$arItem["PRODUCT_ID"]][$arItem["NUMBER_ID"]][] = $arItem;
		}*/

		$currentDateTime = new DateTime();
		$insertDateTime = new DateTime($order["DATE_INSERT"]);
		$insertDateTime->setTime(23, 59, 59);
		$interval = $insertDateTime->diff($currentDateTime);
		$insertHour = $interval->h + ($interval->days * 24);
		$insertDate = $insertDateTime->format('d.m');

		foreach($order["BASKET"] as $k => $arItem){
			$arIDs[$arItem["PRODUCT_ID"]] = $arItem["PRODUCT_ID"];
			for($i = 0; $i < $arItem["QUANTITY"]; $i++){

				$number_id = $i + 1;
				
				$key = "{$order["ID"]}-{$arItem["PRODUCT_ID"]}-{$number_id}";
				if ($arPrint[$key]) {
					$is_print = true;
				} else {
					$is_print = false;
				}

				$arResult["ORDER_ITEMS_MARKET"][] = array(
					"ID" => $order["ID"],
					"ORDER_NUMBER_ID" => $order["ORDER_ID"],
					"ORDER_MARKET_NUMBER" => $order[$arResult["PROP_KEY_MARKET"]],
					"ORDER_COMMENTS" => $order["COMMENTS"],
					"PRODUCT_ID" => $arItem["PRODUCT_ID"],
					"MAXYSS_OP_STICKER" => $order["MAXYSS_OP_STICKER"],
					"OZON_NUMBER" => $order["OZON_NUMBER"],
					"ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
					"SORT_ARTICLE" => $arResult["ARTICLE_BX"][$arItem["PRODUCT_ID"]],
					"INSERT_DATE" => $insertDate,
					"INSERT_HOUR" => $insertHour,
					"SOURCE" => $order["TRADING_NAME"],
					"STICKER" => true,
					"STICKER_PRINT" => $is_print,
					"STICKER_PRINT_HISTORY" => $arPrint[$key] ?? [],
					"NUMBER_ID" => $number_id,
				);
				//$arResult["ORDER_ITEMS"][$arItem["PRODUCT_ID"]][] = $order["ORDER_ID"];
			}

		}
	
	}

}

$arIDsBX = [];
if (count($arIDs) > 0) {
	
	$arFilter = Array(
		"IBLOCK_ID"	=> 16,
		"ID" => $arIDs,
	);
	$arSelect = Array(
		"ID","XML_ID","NAME","PREVIEW_PICTURE", "DETAIL_PICTURE", "IBLOCK_ID", "DETAIL_PAGE_URL", 
		"PROPERTY_WBARTICLE", "PROPERTY_CML2_ARTICLE", "PROPERTY_AEN", "PROPERTY_AEN2", "PROPERTY_barcodes"
	);

	$rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

	while($ob = $rs->GetNextElement()){
		$arFields = $ob->GetFields();
		
		if($arFields["PREVIEW_PICTURE"]) {
			$arFields["PICTURE_SRC"] = CFile::GetPath($arFields["PREVIEW_PICTURE"]);
		} elseif($arFields["DETAIL_PICTURE"]) {
			$arFields["PICTURE_SRC"] = CFile::GetPath($arFields["DETAIL_PICTURE"]);
		}
		
		$arResult["ITEMS"][$arFields["ID"]] = $arFields;

		$arArticleBX[] = $arFields["PROPERTY_CML2_ARTICLE_VALUE"];

		$arIDsBX[] = $arFields["ID"];

		$arXMLIDsBX[] = $arFields["XML_ID"];

	}

	$strSql = "SELECT * FROM ci_catalog_barcode WHERE ARTICLE IN ('" . implode("','", $arArticleBX) . "') ORDER BY ID desc";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);

	while ($row = $results->Fetch()){
		$arResult["BARCODE"][$arResult["ID_BX"][$row["ARTICLE"]]][] = $row["BARCODE"];
	}
	
	$strSql = "SELECT * FROM ci_reserved WHERE PRODUCT_ID IN ('" . implode("','", $arIDs) . "')";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);

	while ($row = $results->Fetch()){
		$arResult["RESERVED"][$row["PRODUCT_ID"]] = $row;
	}
}
//prent($arResult["ORDER_ITEMS_MARKET"]);
// формат: артикул - первый штрихкод товара - номер марки - номер заказа
if ($_REQUEST["order_export_list"]) {
	$APPLICATION->RestartBuffer();
	//ob_start();
	$export = [];
	foreach ($arResult["ORDER_ITEMS_MARKET"] as $k => $v) {
		if ($v['CABINET'] != 'WB_BY') continue;	
		$controlMark = '';
		foreach ($v['STICKER_PRINT_HISTORY'] as $history) {
			if ($history['CONTROL_MARK']) {
				$controlMark = $history['CONTROL_MARK'];
				break;
			}
		}
		
		if (!$controlMark) continue;
		
		$export[] = [
			'ARTICLE' => $v['ARTICLE'],
			'BARCODE' => $arResult["BARCODE"][$v["PRODUCT_ID"]][0] ?? '',
			'CONTROL_MARK' => $controlMark,
			'ORDER_NUMBER' => $v['ORDER_NUMBER_ID'],
		];
		
		if ($export) {
			
		}
	}
	//prent($arResult["ORDER_ITEMS_MARKET"]);die;
   // if (ob_get_length()) {
    //    ob_end_clean();
    //}
    
    //header_remove();

	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment;filename="data.csv"');
	header('Cache-Control: max-age=0');
	
	$output = fopen('php://output', 'w');

	fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

	fputcsv($output, ['ARTICLE', 'BARCODE', 'CONTROL_MARK', 'ORDER_NUMBER'], ';');

	foreach ($export as $row) {
		fputcsv($output, $row, ';');
	}

	fclose($output);

	
	ob_end_flush();
	die;
}

//prent($arResult["ORDER_ITEMS_MARKET"]);
$arResult["EXCLUSIVE_LIST"] = [];
if(count_($arArticleBX) > 0){
	/* смотрим в анализе */
	if($_REQUEST["order_marketplace_submit"]){
		$data = [
			"article" => implode(",", $arArticleBX),
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

		try{
			$json = shell_exec("/usr/bin/php81 -f {$url}");
			//file_put_contents("/home/bitrix/logs/analysis_utils.txt", print_r(["json" => $json], true), 8);
			$tmp = json_decode($json, true);
			foreach($tmp as $k => $v){

				if($v["b_price"] - $v["price"] > 15000){
					$arResult["EXCLUSIVE_LIST"][$v["article"]] = true;
				}
			}

		}catch(Exception $e){
		}
	}

	/**/
	$objSupplier = new CPanelSupplier;

	$arResult["SUPPLIER_LIST"] = $objSupplier->getList();
	foreach($arResult["SUPPLIER_LIST"] as $arSup){
		$arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
	}

	$strSql = "SELECT * FROM ci_purchase WHERE active = 'Y' AND model IN ('" . implode("','", $arArticleBX) . "') AND status <> 'D'";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);

	while ($row = $results->Fetch()){
		$supp_id = $row["supp_id"];
		if ($arResult["PURCHASE"][$row["product_id"]][$supp_id]) {
			$arResult["PURCHASE"][$row["product_id"]][$supp_id]["count"] += 1;
		} else {
			$arResult["PURCHASE"][$row["product_id"]][$supp_id] = [
				"supp_id" => $supp_id,
				"count" => 1,
			];
		}
		//$arResult["PURCHASE"][$row["product_id"]][$supp_id] = $row;
	}

	//список отгрузок
	$arFilter = array(
		"LID" => ['s1', 's2'],
		"STATUS_ID" => ["SE", "TA", "CO","CR", "CL"],
		"!CANCELED" => "Y",
	);

	//$arOrder = $objService->getOrderCache(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);
	$arOrder = $objService->getOrder(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);
	$arOrder = array_reverse( $arOrder );
	foreach($arOrder as $key => $arItem){
		foreach($arItem["BASKET"] as $k => $basket){
			for($i = 1; $i <= $basket["QUANTITY"]; $i++){
				if(in_array($basket["PRODUCT_ID"], $arIDsBX)){
					$arResult["ITEMS_OTGR"][] = array(
						"ID" => $arItem["ID"],
						"PRODUCT_ID" => $basket["PRODUCT_ID"],
						"SITE_ID" => $arItem["LID"],
						"ORDER_NUMBER_ID" => $arItem["ORDER_ID"],
						"ORDER_COMMENTS" => $arItem["COMMENTS"],
					);
				}
			}
		}
	}
	unset($arItem);

	foreach($arResult["ITEMS_OTGR"] as $key => &$arItem){
		//$objRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $arItem["PRODUCT_ID"]), false, false, array('PROPERTY_CML2_ARTICLE'));
        //if ($res = $objRes->GetNext()){
		//	$arItem["ARTICLE"] = $res["PROPERTY_CML2_ARTICLE_VALUE"];
		//	$arItem["SORT_ARTICLE"] = $res["PROPERTY_CML2_ARTICLE_VALUE"];
		//}
		$article = $arResult["ITEMS"][$arItem["PRODUCT_ID"]]['PROPERTY_CML2_ARTICLE_VALUE'] ?? '';
		$arItem["ARTICLE"] = $article;
		$arItem["SORT_ARTICLE"] = $article;
	}
	unset($arItem);

	/*$strSql = "SELECT * FROM ci_price WHERE supplier_id IN ('47', '44', '71', '129')";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){

		for($i = 1; $i <= $row["count"]; $i++){
			$arStock[$row["supplier_id"]][$row["model"]][] = $row;
		}

	}

	// очищаем от склада 
	$arSupp = [47, 44, 129];
	foreach($arResult["ITEMS_OTGR"] as $key => &$arItem){
		if($arItem["ARTICLE"]){
			// switch($arItem["SITE_ID"]){
			// 	case "s1":
			// 		$supp_id = 47;
			// 		break;
			// 	case "s1":
			// 		$supp_id = 44;
			// 		break;
			// 	case "s1":
			// 		$supp_id = 71;
			// 		break;
			// 	case "s1":
			// 		$supp_id = 129;
			// 		break;
			// 	default: break;
			// }
			foreach ( $arSupp as $keySu => $supp_id){
				if( $keySu == array_key_last($arSupp) && !isset($arStock[$supp_id][$arItem["ARTICLE"]])){
					// unset($arResult["ITEMS_OTGR"][$key]);
					break;
				}

				$key_stock = array_keys_($arStock[$supp_id][$arItem["ARTICLE"]]);

				unset($arStock[$supp_id][$arItem["ARTICLE"]][$key_stock[0]]);
				if(count_($arStock[$supp_id][$arItem["ARTICLE"]]) == 0){
					unset($arStock[$supp_id][$arItem["ARTICLE"]]);
				}
			}

		}else{
			unset($arResult["ITEMS_OTGR"][$key]);
		}
	}
	unset($arItem);*/

	$arResult["SHIPMENT"] = array();
	foreach($arResult["ITEMS_OTGR"] as $key => $arItem){
		if ($arItem["SITE_ID"] == 's2') {
			$arResult["SHIPMENT_BY"][$arItem["ARTICLE"]][] = $arItem;
		} else {
			$arResult["SHIPMENT"][$arItem["ARTICLE"]][] = $arItem;
		}
	}

	/*2) Если модель есть в разделе "Готовы к отгрузке", то мы проверим по истории, когда и где закупалась эта модель, а также количество моделей на складе.
	При этом выведем текущее количество моделей на складе и только последнюю закупку модели
	Например:

	Склад (5)
	3. Денис (Москва ПН-ВС 9-15)
	15.35 20.09.2021
	*/


	if(count_($arXMLIDsBX) > 0){
//AND TYPE = 'SUPPLY'
		//$strSql = "SELECT * FROM ci_ms_history WHERE PRODUCT_XML_ID IN ('" . implode("','", $arXMLIDsBX) . "') AND SITE_ID = 's1' ORDER BY TIMESTAMP DESC";// DESC LIMIT 0,100";

		$strSql = "SELECT * FROM ci_ms_history WHERE PRODUCT_XML_ID IN ('" . implode("','", $arXMLIDsBX) . "') AND 
			APPLICABLE = 'Y' AND 
			(
				(SITE_ID = 's1' AND DATA_2_ID = '79ed7d71-0aa6-11ea-0a80-004200039aa4') OR
				(SITE_ID = 's2' AND DATA_2_ID = '6f6d2169-180c-11ea-0a80-00b30004eaef')
			) 
			ORDER BY TIMESTAMP DESC";// DESC LIMIT 0,100";

		
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if ($row['SITE_ID'] == 's1') {
				$arResult["MS_HISTORY"][$row["PRODUCT_XML_ID"]][] = $row;
			} elseif ($row['SITE_ID'] == 's2') {
				$arResult["MS_HISTORY_BY"][$row["PRODUCT_XML_ID"]][] = $row;
			}
		}
	}
//prent($arResult["ORDER_ITEMS_MARKET"]);
//p/rent($arResult["SHIPMENT_BY"]);
	foreach ($arResult["ORDER_ITEMS_MARKET"] as $key => &$arItem) {
		if($arItem["SOURCE"] == 'wb_by') {
			$msHistory = $arResult["MS_HISTORY_BY"];
			if (!$arResult["SHIPMENT_BY"][$arItem["ARTICLE"]]) continue;
		} else {
			$msHistory = $arResult["MS_HISTORY"];
			if (!$arResult["SHIPMENT"][$arItem["ARTICLE"]]) continue;
		}
		if(!$arResult["ITEMS"][$arItem["PRODUCT_ID"]]) continue;
		if($arResult["ITEMS"][$arItem["PRODUCT_ID"]]['LAST_PURCHASE']) {
			$arItem['SKLAD_CNT'] = $arResult["ITEMS"][$arItem["PRODUCT_ID"]]['SKLAD_CNT'];
			continue;
		}

		$item = $arResult["ITEMS"][$arItem["PRODUCT_ID"]];

		if ($arItem['CABINET'] == 'WB_BY' || $arItem['CABINET'] == 'OZON_BY') {
			$strSql = "SELECT * FROM ci_price WHERE model = '{$arItem["ARTICLE"]}' AND active_by = 'Y' AND supplier_id = '44'";
		} else {
			$strSql = "SELECT * FROM ci_price WHERE model = '{$arItem["ARTICLE"]}' AND active = 'Y' AND supplier_id = '47'";
		}
		//prent($strSql);
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$item["SKLAD_CNT"] = $row["count"];
		}

		if($item["SKLAD_CNT"] > 0){

			if($item["SKLAD_CNT"] > 5) $limit = 5; else $limit = $item["SKLAD_CNT"];

			if($msHistory[$item["XML_ID"]] && count($msHistory[$item["XML_ID"]]) > 0){
				$cnt = 0;
				foreach($msHistory[$item["XML_ID"]] as &$ar){
					if( $ar["TYPE"] == "SUPPLY" ){
						if($cnt + $ar["QUANTITY"] < $limit){
							$item["LAST_PURCHASE"][] = $ar;
						}else{
							$ar["QUANTITY"] = $limit - $cnt;
							$item["LAST_PURCHASE"][] = $ar;
							break;
						}
						$cnt += $ar["QUANTITY"];
					} elseif($ar["TYPE"] == "SALES_RETURN") {
						$cnt -= $ar["QUANTITY"];
						$item["LAST_PURCHASE"][] = $ar;
					} elseif($ar["TYPE"] == "MOVE") {
						if ($ar["DATA_2"] != "Дубровка") continue; 
						if($cnt + $ar["QUANTITY"] < $limit){
							$ar["AGENT"] = $ar["DATA_1"];
							$item["LAST_PURCHASE"][] = $ar;
						}else{
							$ar["QUANTITY"] = $limit - $cnt;
							$ar["AGENT"] = $ar["DATA_1"];
							$item["LAST_PURCHASE"][] = $ar;
							break;
						}
						$cnt += $ar["QUANTITY"];
					}
				}
				unset($ar);
			}

		}
		$arItem['SKLAD_CNT'] = $item["SKLAD_CNT"];
		
		$arResult["ITEMS"][$arItem["PRODUCT_ID"]] = $item;
	}
	unset($arItem);
	
	//prent($arResult["ORDER_ITEMS"]);
	if (is_array($arResult['ORDER_ITEMS']) && count($arResult['ORDER_ITEMS']) > 0) {
		foreach ($arResult["ORDER_ITEMS"] as $key => $ar) {
			$arItem = $ar[0];

			if($arItem["LID"] == 's2') {
				$msHistory = $arResult["MS_HISTORY_BY"];
				if (!$arResult["SHIPMENT_BY"][$arItem["ARTICLE"]]) continue;
			} else {
				$msHistory = $arResult["MS_HISTORY"];
				if (!$arResult["SHIPMENT"][$arItem["ARTICLE"]]) continue;
			}
			if(!$arResult["ITEMS"][$arItem["PRODUCT_ID"]]) continue;
			if($arResult["ITEMS"][$arItem["PRODUCT_ID"]]['LAST_PURCHASE']) continue;

			$item = $arResult["ITEMS"][$arItem["PRODUCT_ID"]];
			$item["SKLAD_CNT"] = 0;

			if ($arItem['LID'] == 's2') {
				$strSql = "SELECT * FROM ci_price WHERE model = '{$arItem["ARTICLE"]}' AND active_by = 'Y' AND supplier_id = '44'";
			} else {
				$strSql = "SELECT * FROM ci_price WHERE model = '{$arItem["ARTICLE"]}' AND active = 'Y' AND supplier_id = '47'";
			}
			
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$item["SKLAD_CNT"] = $row["count"];
			}

			if($item["SKLAD_CNT"] > 0){

				if($item["SKLAD_CNT"] > 5) $limit = 5; else $limit = $item["SKLAD_CNT"];

				if($msHistory[$item["XML_ID"]] && count($msHistory[$item["XML_ID"]]) > 0){
					$cnt = 0;
					foreach($msHistory[$item["XML_ID"]] as &$ar){
						if( $ar["TYPE"] == "SUPPLY" ){
							if($cnt + $ar["QUANTITY"] < $limit){
								$item["LAST_PURCHASE"][] = $ar;
							}else{
								$ar["QUANTITY"] = $limit - $cnt;
								$item["LAST_PURCHASE"][] = $ar;
								break;
							}
							$cnt += $ar["QUANTITY"];
						} elseif($ar["TYPE"] == "SALES_RETURN") {
							$cnt -= $ar["QUANTITY"];
							$item["LAST_PURCHASE"][] = $ar;
						} elseif($ar["TYPE"] == "MOVE") {
							if ($ar["DATA_2"] != "Дубровка") continue; 
							if($cnt + $ar["QUANTITY"] < $limit){
								$ar["AGENT"] = $ar["DATA_1"];
								$item["LAST_PURCHASE"][] = $ar;
							}else{
								$ar["QUANTITY"] = $limit - $cnt;
								$ar["AGENT"] = $ar["DATA_1"];
								$item["LAST_PURCHASE"][] = $ar;
								break;
							}
							$cnt += $ar["QUANTITY"];
						}
					}
					unset($ar);
				}

			}
			
			$arResult["ITEMS"][$arItem["PRODUCT_ID"]] = $item;
		}
	}
	//prent($arResult["ITEMS"]);
	//prent($arResult['ORDER_ITEMS']);
	/*foreach($arResult["ITEMS"] as $key => &$arItem){
		if($arResult["SHIPMENT"][$arItem["PROPERTY_CML2_ARTICLE_VALUE"]]){

			$arItem["SKLAD_CNT"] = 0;

			$strSql = "SELECT * FROM ci_price WHERE model = '{$arItem["PROPERTY_CML2_ARTICLE_VALUE"]}' AND active = 'Y' AND supplier_id = '47'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$arItem["SKLAD_CNT"] = $row["count"];
			}

			if($arItem["SKLAD_CNT"] > 0){

				if($arItem["SKLAD_CNT"] > 5) $limit = 5; else $limit = $arItem["SKLAD_CNT"];

				if($arResult["MS_HISTORY"][$arItem["XML_ID"]] && count($arResult["MS_HISTORY"][$arItem["XML_ID"]]) > 0){
					$cnt = 0;
					foreach($arResult["MS_HISTORY"][$arItem["XML_ID"]] as &$ar){
						if( $ar["TYPE"] == "SUPPLY" ){
							if($cnt + $ar["QUANTITY"] < $limit){
								$arItem["LAST_PURCHASE"][] = $ar;
							}else{
								$ar["QUANTITY"] = $limit - $cnt;
								$arItem["LAST_PURCHASE"][] = $ar;
								break;
							}
							$cnt += $ar["QUANTITY"];
						} elseif($ar["TYPE"] == "SALES_RETURN") {
							$cnt -= $ar["QUANTITY"];
							$arItem["LAST_PURCHASE"][] = $ar;
						} elseif($ar["TYPE"] == "MOVE") {
							if ($ar["DATA_2"] != "Дубровка") continue; 
							if($cnt + $ar["QUANTITY"] < $limit){
								$ar["AGENT"] = $ar["DATA_1"];
								$arItem["LAST_PURCHASE"][] = $ar;
							}else{
								$ar["QUANTITY"] = $limit - $cnt;
								$ar["AGENT"] = $ar["DATA_1"];
								$arItem["LAST_PURCHASE"][] = $ar;
								break;
							}
							$cnt += $ar["QUANTITY"];
						}
					}
					unset($ar);
				}

				//if(is_array($arItem["LAST_PURCHASE"]) && count($arItem["LAST_PURCHASE"]) > 0){
				//	foreach($arItem["LAST_PURCHASE"] as $arPurchase){
				//		$md5 = md5($arItem["PROPERTY_CML2_ARTICLE_VALUE"] . $arPurchase["AGENT"]);
				//		if($arPurchase["TYPE"] == "SALES_RETURN") continue;
				//		if($arResult["PURCHASE_LIST"][$md5]){
				//			$arResult["PURCHASE_LIST"][$md5]["QUANTITY"] += $arPurchase["QUANTITY"];
				//		}else{
				//			$arResult["PURCHASE_LIST"][$md5] = [
				//				"ARTICLE" => $arItem["PROPERTY_CML2_ARTICLE_VALUE"],
				//				"SORT_ARTICLE" => $arItem["PROPERTY_CML2_ARTICLE_VALUE"],
				//				"AGENT" => $arPurchase["AGENT"],
				//				"QUANTITY" => $arPurchase["QUANTITY"],
				//			];
				//		}
				//
				//}
				}

			}

		}
	}
	unset($arItem);*/
}

unset($arItem);

if ($arResult["ONLY_UNSENT"]) {
	$ar = [];
	$orderIds = [];
	//prent($arResult["ORDER_ITEMS_MARKET"]);
	foreach ($arResult["ORDER_ITEMS_MARKET"] as $item) {
		if ($item['STICKER_PRINT'] == true || $item['SKLAD_CNT'] == 0) {
			if ($item['SKLAD_CNT'] == 0)
				$orderIds[] = $item['ID'];
			continue;
		}
		$ar[] = $item;
	}
	
	if (count($orderIds) > 0) {
		$ar2 = [];
		foreach ($ar as $item) {
			if (in_array($item['ID'], $orderIds)) {
				continue;
			}
			$ar2[] = $item;
		}
		$ar = $ar2;
	}
	$arResult["ORDER_ITEMS_MARKET"] = $ar;
	//
}

if($_REQUEST["barcode"] && count($_REQUEST["barcode"]) > 0 && $_REQUEST["barcodes_submit"]){
	//prent($_POST);
	/*if(count($_POST["product_id"]) > 0){
		foreach($_POST["product_id"] as $key => $product_id){
			//пишим баркод в таблицу. Сохранение будет выполняться при формировании файла для ВБ
			if(strlen($_POST["barcode"][$product_id]) > 0 && $_POST["barcode"][$product_id] != $_POST["barcode_original"][$product_id]){
				$in = array(
					"PRODUCT_ID" => intval($product_id),
					"BARCODE" => "'".addslashes($_POST["barcode_original"][$product_id])."'",
					"BARCODE_UPDATE" => "'".addslashes($_POST["barcode"][$product_id])."'",
				);

				$DB->Insert("ci_barcode_update", $in, $err_mess.__LINE__);
			}
			//	CIBlockElement::SetPropertyValuesEx($product_id, false, array("AEN" => $_POST["barcode"][$product_id]));
		}
	}*/
	$arLog = false;
	foreach($_REQUEST["barcode"] as $product_id => $bc){

		$article = $arResult["ARTICLE_BX"][$product_id];
		$article = htmlentities($article);
		$article = str_replace("&nbsp;", "", $article);

		$barcode = trim($bc);
		$barcode = htmlentities($barcode);
		$barcode = str_replace("&nbsp;", "", $barcode);
		if( empty($article) or empty($barcode) ) {
			//$arLog .= "<p style='color:red;'>{$article} - {$barcode} . Заполнены не все поля.</p>";
		}elseif( !( $objProduct->findArticle( $article ) ) ){
			$arLog .= "<p style='color:red;'>{$article} - {$barcode} . Такой артикул не существует на сайте.</p>";
		}elseif( $objUtils->checkArtBarcode($article, $barcode) ){
			$arLog .= "<p style='color:red;'>{$article} - {$barcode} . Такой ШК установлен для другого товара, обратитесь к руководителю</p>";
		}elseif( $objUtils->addAltBarcode($article, $barcode) ){
			$arLog .= "<p style='color:green;'>{$article} - {$barcode} добавлен</p>";
		}else{
			$arLog .= "<p style='color:red;'>{$article} - {$barcode} ошибка</p>";
		}
	}
	if($arLog)
		echo $arLog;
}


foreach($arResult["PURCHASE_ITEMS"] as $key => &$arItem){
	$arItem["ORDER_NUMBER_ID"] = $arOrder[$arItem["ORDER_ID"]]["ORDER_NUMBER_ID"];
	$arItem["ORDER_COMMENTS"] = $arOrder[$arItem["ORDER_ID"]]["COMMENTS"];
	$arItem["NAME"] = $arResult["ITEMS"][$arItem["PRODUCT_ID"]]["NAME"];
}
unset($arItem);

$arResult["PURCHASE_ITEMS"] = sort_nested_arrays($arResult["PURCHASE_ITEMS"], array('ORDER_NUMBER_ID' => 'ASC,NULLS', 'NAME' => 'asc'), true);
	
	
$arCnt = [];
if(is_array($arResult['PURCHASE_ITEMS']) && count($arResult['PURCHASE_ITEMS']) > 0){
	foreach($arResult['PURCHASE_ITEMS'] as $arItem){
		$arCnt[$arItem["PRODUCT_ID"]] += 1;
	}
	foreach($arResult['PURCHASE_ITEMS'] as $k => &$v){
		$v["COUNT_PRODUCT"] = $arCnt[$v["PRODUCT_ID"]];
	}
	unset($v);
	$arResult["PURCHASE_ITEMS"] = sort_nested_arrays($arResult["PURCHASE_ITEMS"], array('COUNT_PRODUCT' => 'desc', 'ARTICLE' => 'asc'), true);
}

$arCnt = [];
if(is_array($arResult['ORDER_ITEMS_MARKET']) && count($arResult['ORDER_ITEMS_MARKET']) > 0){
	foreach($arResult['ORDER_ITEMS_MARKET'] as $arItem){
		$arCnt[$arItem["PRODUCT_ID"]] += 1;
	}
	$key = 1;
	foreach($arResult['ORDER_ITEMS_MARKET'] as $k => &$v){
		$v["COUNT_PRODUCT"] = $arCnt[$v["PRODUCT_ID"]];
		if ($v["IS_GROUP"]) {
			$v['SORT_ARTICLE'] = sprintf(
				'%010d-%s',
				99999999999,
				$v['ID']
			);
		} else {
			$v["SORT_ARTICLE"] = (string) ($arCnt[$v["PRODUCT_ID"]] * 10000) . "-" . $v["ARTICLE"];
			$v['SORT_ARTICLE'] = sprintf(
				'%010d-%s',
				9999999999 - $v['COUNT_PRODUCT'],
				$v['ARTICLE']
			);
		}
		
	}
	unset($v);
	$arResult["ORDER_ITEMS_MARKET"] = sort_nested_arrays($arResult["ORDER_ITEMS_MARKET"], array('COUNT_PRODUCT' => 'desc', 'ARTICLE' => 'asc', 'ID' => 'asc'), true);

	foreach($arResult['ORDER_ITEMS_MARKET'] as $k => $v){
		if(in_array($v["ORDER_MARKET_NUMBER"], $arResult["ORDER_W_STICKER"])){
			if(!$arResult["GROUP_ORDER_W_STICKER"][$v["PRODUCT_ID"]]){
				$arResult["GROUP_ORDER_W_STICKER"][$v["PRODUCT_ID"]] = [
					"ARTICLE" => $v["ARTICLE"],
					"COUNT_PRODUCT" => $v["COUNT_PRODUCT"],
					"BARCODES" => ($arResult['BARCODE'][$v["PRODUCT_ID"]] ? $arResult['BARCODE'][$v["PRODUCT_ID"]] : []),
					"STICKERS" => [],
				];
			}
			$arResult["GROUP_ORDER_W_STICKER"][$v["PRODUCT_ID"]]["STICKERS"][] = $v["ORDER_MARKET_NUMBER"];
		}
		
		//$arResult["ORDER_MARKET_NUMBERS"][] = $v[$arResult["PROP_KEY_MARKET"]];
	}
	
		/*foreach ($arResult["ORDER_MARKET_NUMBERS"] as $order_number) {
			if (!file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_number}.pdf")) {
				$arNumber[] = $order_number;
			}
		}*/
}

$arCnt = [];
if(is_array($arResult['ORDER_ITEMS']) && count($arResult['ORDER_ITEMS']) > 0){
	foreach($arResult['ORDER_ITEMS'] as $arItem){
		foreach($arItem as $ar){
			$arCnt[$ar["PRODUCT_ID"]] += 1;
		}
	}
	//prent($arCnt);
	
	foreach($arResult['ORDER_ITEMS'] as $k => $arItem){
		foreach($arItem as $k2 => $ar){
			$arResult['ORDER_ITEMS'][$k][$k2]["COUNT_PRODUCT"] = $arCnt[$ar["PRODUCT_ID"]];
		}
	}
	//prent($arResult['ORDER_ITEMS']);
	//unset($v);
	$arResult["ORDER_ITEMS"] = sort_nested_arrays($arResult["ORDER_ITEMS"], array('COUNT_PRODUCT' => 'desc', 'ARTICLE' => 'asc'), true);
}


$this->IncludeComponentTemplate();
?>
