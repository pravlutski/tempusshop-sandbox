<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
global $USER;
global $DB;
use Bitrix\Main\Loader;

Loader::includeModule('sale');

if (!$USER) {
    die('Access denied');
}

//if (!check_bitrix_sessid()) {
//    die('Invalid session');
//}

$action = $_REQUEST['action'] ?? '';
//$id = (int)($_REQUEST['id'] ?? 0);
//$ids = (array)($_REQUEST['ids'] ?? []);

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
];

if (!in_array($action, $allowedActions)) {
    die('Action not allowed');
}

file_put_contents("/var/www/bitrix_logs/barcode/req.txt", print_r($_POST, true), 8);

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
			} else {
				$result = array(
					'status' => "error",
				);
			}
            break;
			
		case 'get_orders':
			
			$barcode = trim($_POST["barcode"]);
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
				$tradingPlatformsSkip = ['wb', 'ozon'];
				
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
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				while ($row = $results->Fetch()){
					
					//if ($row['TRADING_PLATFORM'] == "sites" && $row['LID'] == "s2") continue;
					if ($row['TRADING_PLATFORM'] == "sites" && $row['LID'] == "s2") {
						$row['TRADING_PLATFORM'] = "sites_s2";
					}
					$result['orders'][] = [
						'ID' => $row['ORDER_ID'],
						'QUANTITY' => intval($row['QUANTITY']),
						'TRADING_PLATFORM' => $row['TRADING_PLATFORM'],
					];
					
				}
			} else {
				$result = array(
					'status' => "error",
				);
			}
			
            break;
			
        case 'check_print_sticker': // проверяем печатался стикер или нет
			
			// STICKER_PRINT
			$order_id = intval($_POST["order_id"]);
			
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
            break;
		
        case 'set_print_sticker':
			
			$order_id = intval($_POST["order_id"]);
			
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
            break;
		case 'add_history':
			$data = $_POST["data"];
			$data["date"] = date("Y-m-d H:i:s");
			$data["user_id"] = $USER->getID();
			
			$fileHistory = "/var/www/bitrix_logs/barcode/logs/" . date("Y-m-d") . ".txt";
			file_put_contents($fileHistory, serialize($data) . "\r\n", 8);
			
			$result = [
				'status' => 'ok'
			];
            break;
		case 'get_history':
			$filter = $_POST["filter"];
			$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
			$user_id = $USER->getID();
			
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
							if ($data && $data["user_id"] == $user_id) {
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
        default:
            $result = ['status' => 'error', 'message' => 'Unknown action'];
    }
    file_put_contents("/var/www/bitrix_logs/barcode/req.txt", print_r($result, true), 8);
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
	file_put_contents("/var/www/bitrix_logs/barcode/req_error.txt", print_r($e->getMessage(), true), 8);
    die('Error: '.$e->getMessage());
}
