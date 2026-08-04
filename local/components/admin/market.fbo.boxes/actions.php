<?php
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
	'set_settings', 
];

if (!in_array($action, $allowedActions)) {
    die('Action not allowed');
}

$userID = $USER->getID();

$logger = new TsLogger("/utils/market.fbo.boxes/");

try {
    switch ($action) {
        case 'set_settings':
			$settings = $_POST["settings"];
			
			CProSet::setOption("SETTINGS_UTILS_FBO_BOXES", serialize($settings));
			$result = [
				'status' => 'ok',
			];
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
