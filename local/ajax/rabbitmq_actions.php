<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
global $USER;

if (!$USER->IsAdmin()) {
    die('Access denied');
}

//if (!check_bitrix_sessid()) {
//    die('Invalid session');
//}

$action = $_REQUEST['action'] ?? '';
$id = (int)($_REQUEST['id'] ?? 0);
$ids = (array)($_REQUEST['ids'] ?? []);

if (empty($action) || ($id <= 0 && count($ids) == 0)) {
    die('Invalid parameters');
}

// Разрешенные действия
$allowedActions = ['send_order', 'send_order_list', 'send_product', 'send_product_list'];

if (!in_array($action, $allowedActions)) {
    die('Action not allowed');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');

$syncHelper = new SyncHelper();

			
try {
    // Здесь будет логика работы с RabbitMQ
    switch ($action) {
        case 'send_order':
            $syncHelper->sendOrder($id, false);
			$result = ['status' => 'ok'];
            break;

        case 'send_order_list':
			foreach ($ids as $id) {
				$syncHelper->sendOrder($id, false);
			}
			$result = ['status' => 'ok'];
            break;

        case 'send_product':
			$syncHelper->sendProduct([$id], false);
			$result = ['status' => 'ok'];
            break;

        case 'send_product_list':
			foreach (array_chunk($ids, 100) as $arIDs) {
				$syncHelper->sendProduct($arIDs, false);
			}
			$result = ['status' => 'ok'];
            break;
			
        default:
            $result = ['status' => 'error', 'message' => 'Unknown action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
	file_put_contents("/var/www/bitrix_logs/rabbitmq/rabbitmq_actions_error.txt", print_r($e->getMessage()), 8);
    die('Error: '.$e->getMessage());
}
