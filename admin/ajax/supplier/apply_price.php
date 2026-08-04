<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objSupplier = new CPanelSupplier;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	//AddMessage2Log($_POST);
	$result = $objSupplier->apply_price($_POST);
	if($result === true){
		$res["status"] = "ok";
		$res["data"] = "ok";
	}elseif(is_int($result)){
		$res["status"] = "ok";
		$res["data"] = "new";
	}else{
		$res["status"] = "error";
		$res["data"] = "Сохранить не удалось";
	}
}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}
$res["sdf"] = $_POST;
		$res["status"] = "ok";
		$res["data"] = "ok";
		
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
