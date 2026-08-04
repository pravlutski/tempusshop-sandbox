<?php
if (!defined('BX_SECURITY_SESSION_READONLY')) {
    define('BX_SECURITY_SESSION_READONLY', true);
}
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
global $USER;
global $DB;
use Bitrix\Main\Loader;

Loader::includeModule('sale');
if (!class_exists('OrderPrintManager')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/OrderPrintManager.php';
}
if (!$USER) {
    die('Access denied');
}

//if (!check_bitrix_sessid()) {
//    die('Invalid session');
//}

$action = $_REQUEST['action'] ?? '';
$type_order = $_REQUEST['type_order'] ?? '';
//$id = (int)($_REQUEST['id'] ?? 0);
//$ids = (array)($_REQUEST['ids'] ?? []);

$accessGroup = $USER->GetUserGroupArray();

if (empty($action)) {
    die('Invalid parameters');
}

// Разрешенные действия
$allowedActions = [
	'scan_barcode', 
	'get_orders', 
	'check_print_sticker', 
	'set_print_sticker', 
	'add_history',
	'get_history',
	'set_settings',
	'get_print_history',
	'delete_print_item',
];

if (!in_array($action, $allowedActions)) {
    die('Action not allowed');
}


//if (!in_array($type_order, ['FBS', 'FBO'])) {
//    die('type_order not allowed');
//}

$userID = $USER->getID();

$logger = new TsLogger("/utils/barcodes/");

$needLog = !in_array($action, ['get_print_history', 'get_history']) ?? false;

if ($needLog)
	$logger->log("LOG", "Запрос", ['userID' => $userID, '_REQUEST' => $_REQUEST]); 

function getOrdersByBarcode($barcode = '', $full_list = false) {
	global $DB;		
	
	$productID = false;
	$strSql = "SELECT * FROM ci_catalog_barcode WHERE BARCODE = '".$barcode."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		$article = $row["ARTICLE"];
		$productID = $row["PRODUCT_ID"];
	} else {
		$result = array(
			'status' => "error",
		);
	}
	
	if ($productID) {
	
		$result = array(
			'status' => "ok",
			'article' => $article,
			'productID' => $productID,
			'orders' => [],
		);

		$statuses = [
			'SE', // готов к отправке
			'CL', // сборка
			'CO', // готов к доставке
			'CR', // выдан курьеру на доставку
			'TA', // Самовывоз 
		];

		//$statuses = ['F'];
		//$tradingPlatformsSkip = ['wb', 'ozon', 'yandex'];//'wbip', 
		$tradingPlatformsSkip = getTpSkip();
		
		/*$arTrading = [
			'sites_s2' => [
				'name' => 'Полка для Минска',
				'priority' => 10,
			], 
			'onliner' => [
				'name' => 'Полка онлайнер (Минск)',
				'priority' => 15,
			], 
			'21_vek' => [
				'name' => 'Полка 21-й век',
				'priority' => 20,
			], 
			'sites' => [
				'name' => 'Полка для Москвы',
				'priority' => 50,
			], 
			'avito_export' => [
				'name' => 'Полка avito',
				'priority' => 60,
			], 
			'yamarket_marketp_dbs' => [
				'name' => 'Полка yamarket_marketp_dbs',
				'priority' => 100,
			], 
			'YM_780792' => [
				'name' => 'Полка YM_780792',
				'priority' => 110,
			], 
			'YM_FBS' => [
				'name' => 'Полка YM_FBS',
				'priority' => 120,
			], 
			'YM_DBS' => [
				'name' => 'Полка YM_DBS',
				'priority' => 130,
			], 
			'sber' => [
				'name' => 'Полка sber',
				'priority' => 140,
			], 
			'yandex' => [
				'name' => '',
				'priority' => 150,
			], 
			'wb' => [
				'name' => '',
				'priority' => 160,
			], 
			'ozon' => [
				'name' => '',
				'priority' => 170,
			], 
			'wb_fbo' => [
				'name' => 'Полка FBO WB',
				'priority' => 200,
			], 
			'ozon_fbo' => [
				'name' => 'Полка FBO Ozon',
				'priority' => 200,
			], 
		];*/
		$settings = unserialize(CProSet::getOption("SETTINGS_UTILS_BARCODE"));
		$arTrading = $settings['trading_priority'];
		//$result['vsvsvsv'] = $arTrading;
		$strSql = "SELECT DISTINCT 
			o.ID AS ORDER_ID,
			o.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
			o.LID AS LID,
			o.DATE_INSERT,
			o.PRICE,
			o.STATUS_ID,
			o.USER_ID,
			b.QUANTITY AS QUANTITY,
			tp.CODE AS TRADING_PLATFORM,
			tp.ID AS TRADING_PLATFORM_ID,
			tpo.PARAMS AS TRADING_PARAMS
		FROM 
			b_sale_order o
			INNER JOIN b_sale_basket b ON b.ORDER_ID = o.ID
			INNER JOIN b_sale_tp_order tpo ON tpo.ORDER_ID = o.ID
			INNER JOIN b_sale_tp tp ON tp.ID = tpo.TRADING_PLATFORM_ID
		WHERE 
			b.PRODUCT_ID IN ({$productID})
			AND o.STATUS_ID IN ('".implode("','", $statuses)."')
			AND o.CANCELED != 'Y'
		ORDER BY 
			o.DATE_INSERT DESC";
		// AND tp.CODE NOT IN ('".implode("','", $tradingPlatformsSkip)."')
		
		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/components/admin/utils.barcodes/sql.txt', print_r($strSql, true));
		$orders = [];
		$yaNip = []; // очистить надо от nip яндекс
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			
			//if ($row['TRADING_PLATFORM'] == "sites" && $row['LID'] == "s2") continue; 
			if ($row['TRADING_PLATFORM'] == "sites" && $row['LID'] == "s2") {
				$row['TRADING_PLATFORM'] = "sites_s2";
			}
			if ($row['USER_ID'] == 81140 || $row['TRADING_PLATFORM'] == 'YM_780792') {
				$row['TRADING_PLATFORM'] = "yandex";
			}
			
			if (!$full_list && $row['TRADING_PLATFORM'] && in_array($row['TRADING_PLATFORM'], $tradingPlatformsSkip)) {
				continue;
			}

			if ($row['TRADING_PLATFORM'] == 'yandex') {
				$setting = unserialize($row['TRADING_PARAMS']);
				if ($setting['CAMPAIGN_ID'] && $setting['CAMPAIGN_ID'] == 148300505) { // пропускаем nip заказы
					continue;
				}
			}
			
			$priority = $arTrading[$row['TRADING_PLATFORM']]['priority'] ?? 1000;
			$shelf_name = $arTrading[$row['TRADING_PLATFORM']]['name'] ?? '';
			
			$orders[] = [
				'ID' => $row['ORDER_ID'],
				'PRODUCT_ID' => $productID,
				'QUANTITY' => intval($row['QUANTITY']),
				'TRADING_PLATFORM' => $row['TRADING_PLATFORM'],
				'PRIORITY' => $priority,
				'SHELF_NAME' => $shelf_name,
				'TIMESTAMP' => strtotime($row['DATE_INSERT']),
			];
		}
		
		// если нет заказов смотрим в закупке без номера. перемещение или тип того
		if (1==1 || count($orders) == 0) {
			$strSql = "SELECT 
				id as ID, site_id as SITE_ID, COUNT(product_id) as QUANTITY, timestamp as TIMESTAMP 
			FROM ci_purchase 
			WHERE 
				product_id = '".$productID."' AND active = 'Y' AND (order_id = '0' OR order_id IS NULL) 
			GROUP BY product_id";
			
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				
				if ($row['SITE_ID'] == 's1' || $row['SITE_ID'] == 's1_nkz') {
					$tp = 'Москва';
				} else {
					$tp = 'Минск';
				}
				
				$shelf_name = $arTrading['purchase_' . $row['SITE_ID']]['name'] ?? '';
				$priority = $arTrading['purchase_' . $row['SITE_ID']]['priority'] ?? 1000;

				$orders[] = [
					'ID' => $row['ID'] * 100,
					'PRODUCT_ID' => $productID,
					'QUANTITY' => $row['QUANTITY'],
					'TRADING_PLATFORM' => $tp,
					//'SHELF_NAME' => 'Полка закупки',
					'PRIORITY' => $priority,
					'SHELF_NAME' => $shelf_name,
					'TIMESTAMP' => strtotime($row['TIMESTAMP']),
				];
			}
		}
		
		$orders = sort_nested_arrays_v2($orders, ['PRIORITY' => 'asc', 'TIMESTAMP' => 'desc']);
		
		// тут костылёк. смотрим показывались ли товары хоть раз
		$orderIds = array_column($orders, 'ID');
			
		$strSql = "SELECT 
			ORDER_ID, COUNT(ORDER_ID) as COUNT_SCAN 
			FROM ci_order_print_scan WHERE ORDER_ID IN ('".implode("','", $orderIds)."') AND PRODUCT_ID = '{$productID}' 
			GROUP BY ORDER_ID";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$orderScanned = [];
		while ($row = $results->Fetch()){
			$orderScanned[$row['ORDER_ID']] = intval($row['COUNT_SCAN']);
		}

		// оставляем в массиве только не просмотренные
		foreach ($orders as $item) {
			if ($orderScanned[$item['ID']]) {
				$item['QUANTITY'] -= $orderScanned[$item['ID']];
			}
			
			if ($item['QUANTITY'] > 0) {
				$result['orders'][] = $item;
			}
		}
		unset($item);
	} else {
		$result = array(
			'status' => "error",
		);
	}
	return $result;
}

function getTpSkip () {
	$cabinets = $_REQUEST['cabinets'] ?? [];
	
	$tradingPlatformsSkip = [];
	if (is_array($cabinets) && in_array('WB_WR', $cabinets)) {
		$tradingPlatformsSkip[] = 'wb';
	}
	if (is_array($cabinets) && in_array('OZON_IP', $cabinets)) {
		$tradingPlatformsSkip[] = 'ozon';
	}
	if (is_array($cabinets) && in_array('YANDEX', $cabinets)) {
		$tradingPlatformsSkip[] = 'yandex';
	}
	if (is_array($cabinets) && in_array('WB_IP', $cabinets)) {
		$tradingPlatformsSkip[] = 'wbip';
	}
	if (is_array($cabinets) && in_array('AVITO', $cabinets)) {
		$tradingPlatformsSkip[] = 'avito_retail';
	}
	
	return $tradingPlatformsSkip;
}

try {
    switch ($action) {
        case 'scan_barcode':
			
			$barcode = trim($_POST["barcode"]);
			$strSql = "SELECT * FROM ci_catalog_barcode WHERE BARCODE = '".$barcode."'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$result = [
					'status' => "ok",
					'article' => $row["ARTICLE"],
				];
				
				if ($type_order == 'BY') break;
				
				$result_orders = getOrdersByBarcode($barcode, true);
				$logger->log("LOG", "scan_barcode result_orders", $result_orders); 
				
				if (is_array($result_orders['orders']) && count($result_orders['orders']) > 0) {
					//$tradingPlatformsSkip = ['wb', 'ozon', 'yandex'];//'wbip', 
					$tradingPlatformsSkip = getTpSkip();
					
					// оставляем только те что не печатались
					$orders = [];
					foreach ($result_orders['orders'] as $k => $v) {
						$printHistory = OrderPrintManager::getPrintHistoryGroup($v['ID'], $v['PRODUCT_ID']);
						
						if (is_array($printHistory) && count($printHistory) != $v['QUANTITY']) {
							$orders[] = $v;
						}
					}
					$result_orders['orders'] = $orders;
					
					if (in_array($result_orders['orders'][0]['TRADING_PLATFORM'], $tradingPlatformsSkip)) {
						// проходим по массиву и ищем которые не печатались
						$needPrintFbs = false;
						foreach ($result_orders['orders'] as $k => $v) {
							if (in_array($v['TRADING_PLATFORM'], $tradingPlatformsSkip)) {
								$printHistory = OrderPrintManager::getPrintHistoryGroup($v['ID'], $v['PRODUCT_ID']);
								
								if ($printHistory && is_array($printHistory) && count($printHistory) != $v['QUANTITY']) {
									$needPrintFbs = true;
									$logger->log("LOG", "printHistory", $printHistory); 
									break;
								}
							}
						}
						$logger->log("LOG", "needPrintFbs", $needPrintFbs); 
						if (!$needPrintFbs) {
							$result['orders'] = [];
							foreach ($result_orders['orders'] as $k => $v) {
								if (!in_array($v['TRADING_PLATFORM'], $tradingPlatformsSkip)) {
									$result['orders'][] = $v;
								} elseif (!$result['current_order']) {
									$result['current_order'] = $v;
								}
							}
							
						}
						
					} else {
						$result['orders'] = $result_orders['orders'];
					}
				}
			} else {
				$result = array(
					'status' => "error",
				);
			}
            break;
			
		case 'get_orders':
			
			$barcode = trim($_POST["barcode"]);
			
			if ($type_order == 'FBS') {
				/*$productID = false;
				$strSql = "SELECT * FROM ci_catalog_barcode WHERE BARCODE = '".$barcode."'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					$article = $row["ARTICLE"];
					$productID = $row["PRODUCT_ID"];
				} else {
					$result = array(
						'status' => "error",
					);
				}
				
				if ($productID) {
					$result = array(
						'status' => "ok",
						'article' => $article,
						'productID' => $productID,
						'orders' => [],
					);

					$statuses = [
						'SE', // готов к отправке
						'CL', // сборка
						'CO', // готов к доставке
						'CR', // выдан курьеру на доставку
						'TA', // Самовывоз 
					];

					//$statuses = ['F'];
					$tradingPlatformsSkip = ['wb', 'ozon'];
					$arPriority = [
						'sites_s2' => 10, 
						'onliner' => 15, 
						'21_vek' => 20, 
						'sites' => 50, 
						'avito_export' => 100, 
						'yamarket_marketp_dbs' => 150, 
						'YM_780792' => 200, 
						'YM_FBS' => 250, 
						'YM_DBS' => 300, 
						'sber' => 310, 
						'wb_fbo' => 350, 
						'ozon_fbo' => 400, 
					];

					$strSql = "SELECT DISTINCT 
						o.ID AS ORDER_ID,
						o.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
						o.LID AS LID,
						o.DATE_INSERT,
						o.PRICE,
						o.STATUS_ID,
						b.QUANTITY AS QUANTITY,
						tp.CODE AS TRADING_PLATFORM,
						tp.ID AS TRADING_PLATFORM_ID
					FROM 
						b_sale_order o
						INNER JOIN b_sale_basket b ON b.ORDER_ID = o.ID
						INNER JOIN b_sale_tp_order tpo ON tpo.ORDER_ID = o.ID
						INNER JOIN b_sale_tp tp ON tp.ID = tpo.TRADING_PLATFORM_ID
					WHERE 
						b.PRODUCT_ID IN ({$productID})
						AND o.STATUS_ID IN ('".implode("','", $statuses)."')
						AND tp.CODE NOT IN ('".implode("','", $tradingPlatformsSkip)."')
						AND o.CANCELED != 'Y'
					ORDER BY 
						o.DATE_INSERT DESC";
					
					$orders = [];
					$results = $DB->Query($strSql, false, $err_mess.__LINE__);
					while ($row = $results->Fetch()){
						
						//if ($row['TRADING_PLATFORM'] == "sites" && $row['LID'] == "s2") continue;
						if ($row['TRADING_PLATFORM'] == "sites" && $row['LID'] == "s2") {
							$row['TRADING_PLATFORM'] = "sites_s2";
						}
						
						$priority = $arPriority[$row['TRADING_PLATFORM']] ?? 1000;
						$orders[] = [
							'ID' => $row['ORDER_ID'],
							'QUANTITY' => intval($row['QUANTITY']),
							'TRADING_PLATFORM' => $row['TRADING_PLATFORM'],
							'PRIORITY' => $priority,
						];
						
					}
					
					// если нет заказов смотрим в закупке без номера. перемещение или тип того
					if (count($orders) == 0) {
						$strSql = "SELECT 
							id as ID, site_id as SITE_ID, COUNT(product_id) as QUANTITY 
						FROM ci_purchase 
						WHERE 
							product_id = '".$productID."' AND active = 'Y' AND (order_id = '0' OR order_id IS NULL) 
						GROUP BY product_id";
						
						$results = $DB->Query($strSql, false, $err_mess.__LINE__);
						while ($row = $results->Fetch()){
							
							if ($row['SITE_ID'] == 's1') {
								$tp = 'Москва';
							} else {
								$tp = 'Минск';
							}
							
							$orders[] = [
								'ID' => $row['ID'] * 100,
								'QUANTITY' => $row['QUANTITY'],
								'TRADING_PLATFORM' => $tp,
							];
						}
					}
					
					$orders = sort_nested_arrays_v2($orders, ['PRIORITY' => 'asc']);
					
					// тут костылёк. смотрим показывались ли товары хоть раз
					$orderIds = array_column($orders, 'ID');
						
					$strSql = "SELECT ORDER_ID, COUNT(ORDER_ID) as COUNT_SCAN FROM ci_order_print_scan WHERE ORDER_ID IN ('".implode("','", $orderIds)."') GROUP BY ORDER_ID";
					$results = $DB->Query($strSql, false, $err_mess.__LINE__);
					$orderScanned = [];
					while ($row = $results->Fetch()){
						$orderScanned[$row['ORDER_ID']] = intval($row['COUNT_SCAN']);
					}
					//$result['orderScanned'] = $orderScanned;
					
					// оставляем в массиве только не просмотренные
					foreach ($orders as $item) {
						if ($orderScanned[$item['ID']]) {
							$item['QUANTITY'] -= $orderScanned[$item['ID']];
						}
						
						if ($item['QUANTITY'] > 0) {
							$result['orders'][] = $item;
						}
					}
					unset($item);
					
					$result['orders'] = array_slice($result['orders'], 0, 1);
					//$result['orders'] = $orders;
					
					if (is_array($result['orders']) && count($result['orders']) > 0) {
						// добавляем в таблицу что просмотрено

						$in = array(
							"ORDER_ID" => "'".addslashes($result['orders'][0]['ID'])."'",
							"USER_ID" => "'".addslashes($userID)."'",
						);
						//prent($in);
						$DB->Insert("ci_order_print_scan", $in, $err_mess.__LINE__);
						
					}
					
				} else {
					$result = array(
						'status' => "error",
					);
				}*/
				
					
				$result = getOrdersByBarcode($barcode);
				
				if (is_array($result['orders']) && count($result['orders']) > 0) {
					// добавляем в таблицу что просмотрено 

					$result['orders'] = array_slice($result['orders'], 0, 1);
					
					$in = array(
						"ORDER_ID" => "'".addslashes($result['orders'][0]['ID'])."'",
						"PRODUCT_ID" => "'".addslashes($result['orders'][0]['PRODUCT_ID'])."'",
						"USER_ID" => "'".addslashes($userID)."'",
					);
					$DB->Insert("ci_order_print_scan", $in, $err_mess.__LINE__);
					
					$logger->log("LOG", "Добавляем в просмотренные", [$barcode, $result]); 
				}
				
			} elseif ($type_order == 'BY') {
				/*$result = getOrdersByBarcode($barcode);
				
				if (is_array($result['orders']) && count($result['orders']) > 0) {
					// добавляем в таблицу что просмотрено 

					$result['orders'] = array_slice($result['orders'], 0, 1);
					
					$in = array(
						"ORDER_ID" => "'".addslashes($result['orders'][0]['ID'])."'",
						"PRODUCT_ID" => "'".addslashes($result['orders'][0]['PRODUCT_ID'])."'",
						"USER_ID" => "'".addslashes($userID)."'",
					);
					$DB->Insert("ci_order_print_scan", $in, $err_mess.__LINE__);
					
					$logger->log("LOG", "Добавляем в просмотренные", [$barcode, $result]); 
				}*/
				$result = getOrdersByBarcode($barcode);
				$result['orders'] = [];
				/*array(
					'status' => "ok",
					'article' => $article,
					'productID' => $productID,
					'orders' => [],
				);*/
			} else {
				$result = array(
					'status' => "ok",
				);
			}

            break;
			
        case 'check_print_sticker': // проверяем печатался стикер или нет
			
			$order_id = intval($_POST["order_id"]);
			if ($type_order == 'FBS' && 1==2) { 
				
				$order = Bitrix\Sale\Order::load($order_id);
				$propertyCollection = $order->getPropertyCollection();
				
				$propStickerPrint = $propertyCollection->getItemByOrderPropertyId(94);

				//if ($propStickerPrint) {
				//	$stickerPrint = $propStickerPrint->getValue();
				//}
				if ($prop = $propertyCollection->getItemByOrderPropertyCode('STICKER_PRINT')) {
					$stickerPrint = $prop->getValue();
				}
				
				$result = array(
					'status' => "ok",
					'print_sticker' => ($stickerPrint && $stickerPrint == 'Y' ? true : false),
				);
			} else {
				$product_id = intval($_POST["product_id"]);
				$number_id = intval($_POST["number_id"]);
				
				$printHistory = OrderPrintManager::getPrintHistory($order_id, $product_id);
				$printCnt = [];
				$is_print = false;
				foreach ($printHistory as $arItem) {
					if (!$arItem["PRODUCT_ID"] || !$arItem["NUMBER_ID"]) continue;
					//$arPrint[$arItem["PRODUCT_ID"]][$arItem["NUMBER_ID"]][] = $arItem;
					
					if ($arItem["NUMBER_ID"] == $number_id) {
						$is_print = true;
					}
					
					$printCnt[$arItem["NUMBER_ID"]] = true;
				}
				
				$strSql = "SELECT QUANTITY FROM b_sale_basket WHERE ORDER_ID = '".$order_id."' AND PRODUCT_ID = '".$product_id."'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					$quantity = intval($row["QUANTITY"]);
					
					/*if (count($printHistory) >= $quantity) {
						$result = [
							'status' => "ok",
							'print_sticker' => true,
						];
					} else {
						$result = [
							'status' => "ok",
							'print_sticker' => false,
						];
					}*/
					if ($is_print && count($printCnt) <= $quantity) {
						$result = [
							'status' => "ok",
							'print_sticker' => true,
							'quantity' => $quantity,
							'printCnt' => $printCnt,
							'is_print' => $is_print,
						];
					} else {
						$result = [
							'status' => "ok",
							'print_sticker' => false,
							'quantity' => $quantity,
							'printCnt' => $printCnt,
							'is_print' => $is_print,
						];
					}
					
					// если стикер не печататлся костылим. надо смотреть нет ли этой позиции в более приоритетных заказа
					if (!$result['print_sticker']) {
						
					}
					
				} else {
					$result = array(
						'status' => "error",
						'message' => 'Не найден товар в заказе',
					);
				}
				
			}

            break;
		
        case 'set_print_sticker':
			
			$order_id = intval($_POST["order_id"]);
			
			if ($type_order == 'FBS') {
				if ($_POST["print_sticker"]) {
					$print_sticker = 'Y';
				} else {
					$print_sticker = 'N';
				}
				
				$order = Bitrix\Sale\Order::load($order_id);
				$propertyCollection = $order->getPropertyCollection();
				
				//$propertyValue = $propertyCollection->getItemById(94);
				
				if ($prop = $propertyCollection->getItemByOrderPropertyCode('STICKER_PRINT')) {
					$r = $prop->setField('VALUE', $print_sticker);
					if ($r->isSuccess()) {
						$result = array(
							'status' => "ok",
						);
						$order->save();
					} else {
						$result = array(
							'status' => "error",
							'message' => $r->getErrorMessages(),
						);
					}
				} else {
					$result = array(
						'status' => "error",
						'message' => 'Ошибка получения свойства',
					);
				}
			}

            break;
		case 'add_history':
			$data = $_POST["data"];
			$data["date"] = date("Y-m-d H:i:s");
			$data["user_id"] = $userID;
			
			$fileHistory = "/var/www/bitrix_logs/barcode/logs/" . date("Y-m-d") . ".txt";
			file_put_contents($fileHistory, serialize($data) . "\r\n", 8);
			
			$result = [
				'status' => 'ok'
			];
            break;
		case 'get_history':
			$filter = $_POST["filter"];
			$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
			
			$fileHistory = "/var/www/bitrix_logs/barcode/logs/" . date("Y-m-d") . ".txt";
			
			$history = [];
			
			if (file_exists($fileHistory)) {
				$lines = [];
				$fp = fopen($fileHistory, 'r');
				$pos = -2;
				$currentLine = '';
				
				while ($limit > 0) {
					// Ищем начало строки
					while (fseek($fp, $pos, SEEK_END) !== -1) {
						$char = fgetc($fp);
						if ($char === "\n") {
							// Нашли конец строки
							break;
						}
						$currentLine = $char . $currentLine;
						$pos--;
					}
					
					if (!empty($currentLine)) {
						try {
							$data = unserialize(trim($currentLine));
							if ($data && $data["user_id"] == $userID && $data["type_order"] == $type_order) {
								array_unshift($lines, $data);
								$limit--;
							}
						} catch (Exception $e) {
						}
						$currentLine = '';
					}
					
					if (ftell($fp) <= 1) {
						break;
					}
					
					$pos--;
				}
				
				fclose($fp);
				$history = $lines;
			}
			
			$result = [
				'status' => 'ok',
				'history' => array_reverse($history)
			];
            break;

        case 'set_settings':
			$settings = $_POST["settings"];
			file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/components/admin/utils.barcodes/templates/.default/asd.txt', print_r($settings, true));
			CProSet::setOption("SETTINGS_UTILS_BARCODE", serialize($settings));
			$result = [
				'status' => 'ok',
				'asdsad' => $settings
			];
			break;
        case 'get_print_history':
			$orderIDs = $_REQUEST["orders"];
			
			if (is_array($orderIDs) && count($orderIDs) > 0) {
				$orderIDs = array_diff($orderIDs, array(''));
				$orderIDs = array_unique($orderIDs);
				
				$printHistory = OrderPrintManager::getPrintHistory($orderIDs);
				$result = [
					'status' => 'ok',
					'history' => $printHistory,
				];
			} else {
				$result = [
					'status' => 'ok',
					'history' => [],
				];
			}

			break;
        case 'delete_print_item':
			$recordId = intval($_REQUEST["record_id"]);
			
			$intersection = array_intersect([1, 19], $accessGroup);

			if (!empty($intersection)) {
				if (OrderPrintManager::deletePrintRecord($recordId)) {
					$result = [
						'status' => 'ok',
					];
				} else {
					$result = [
						'status' => 'error', 
						'message' => 'Unknown error'
					];
				}
			} else {
				$result = [
					'status' => 'error', 
					'message' => 'Нет доступа',
				];
			}

			break;
        default:
            $result = ['status' => 'error', 'message' => 'Unknown action'];
    }
	
	if ($needLog)
		$logger->log("LOG", "Ответ", ['userID' => $userID, 'result' => $result]); 
	
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
	$logger->log("ERROR", "Ошибка", ['userID' => $userID, 'error' => $e->getMessage()]); 
    die('Error: '.$e->getMessage());
}
