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

if (empty($action)) {
    die('Invalid parameters');
}

// Разрешенные действия
$allowedActions = [
	'get_article', 
	'check_article', 
	'find_order', 
	'create_return', 
	'get_settings', 
	'set_settings', 
];

if (!in_array($action, $allowedActions)) {
    die('Action not allowed');
}

//file_put_contents("/var/www/bitrix_logs/barcode/req.txt", print_r($_POST, true), 8);
$IBLOCK_ID = 16;
$ms = new MoyskladAPI("s1");

try {
    switch ($action) {
        case 'get_article':
			$barcode = trim($_POST["barcode"]);
			$strSql = "SELECT * FROM ci_catalog_barcode WHERE BARCODE = '".$barcode."'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$result = [
					'success' => true,
					'article' => $row["ARTICLE"],
				];
			} else {
				$result = [
					'success' => false,
					'message' => 'ШК не найден',
				];
			}
			break;
        case 'check_article':
			$article = trim($_POST["article"]);
			$arFilter = ["IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_CML2_ARTICLE" => $article];
			$arSelect = [
				'ID', 
			];
			$res = CIBlockElement::GetList([], $arFilter, false, false, );
			if ($ob = $res->GetNextElement()){
				$result = [
					'success' => true,
					'exists' => true,
				];
			} else {
				$result = [
					'success' => true,
				];
			}
			break;
        case 'find_order':
			$orderNumber = intval($_POST["orderNumber"]);
			
			if ($orderNumber > 0) {
				$strSql = "SELECT * FROM ci_ms_order WHERE ORDER_NUMBER = '".$orderNumber."'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					$result = [
						'success' => true,
						'article' => $row["ARTICLE"],
					];
				} else {
					$result = [
						'success' => false,
						'message' => 'ШК не найден',
					];
				}
			}

			$result = [
				'success' => false,
				'message' => 'Заказ не найден',
			];
			break;
        case 'create_return':
			$result = [
				'success' => false,
				'message' => 'Возврат не создан',
			];
			break;
        case 'get_settings':
			$result = [
				'success' => false,
				'message' => 'Ошибка получения настроеек',
			];
			break;
        case 'set_settings':
			break;
        default:
            $result = ['status' => 'error', 'message' => 'Unknown action'];
    }
    //file_put_contents("/var/www/bitrix_logs/barcode/req.txt", print_r($result, true), 8);
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
	//file_put_contents("/var/www/bitrix_logs/barcode/req_error.txt", print_r($e->getMessage(), true), 8);
    die('Error: '.$e->getMessage());
}
