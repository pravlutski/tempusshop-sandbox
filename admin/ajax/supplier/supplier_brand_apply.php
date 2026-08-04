<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objSupplier = new CPanelSupplier;
$file_log = "/var/www/bitrix/data/www/tempusshop.ru/admin/log/supplier_brand_apply_" . date("Y-m-d") . ".txt";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	
	global $USER;
	$ar = array(
		"date" => date("Y-m-d H:i:s"),
		"USER_ID" => $USER->getID(),
		"POST" => $_POST
	);

	$result = $objSupplier->applyBrandSale($_POST);
	file_put_contents($file_log, print_r($ar, true) . "\r\n", FILE_APPEND | LOCK_EX);
	if($result === true){
		$res["status"] = "ok";
		$res["data"] = "ok";
	}else{
		$res["status"] = "error";
		$res["data"] = "Сохранить не удалось";
	}
}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
