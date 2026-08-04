<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
global $USER;
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objBrand = new CPanelBrand;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $USER->isAdmin()) {
	$brand_id = intval($_POST["id"]);
	$result = $objBrand->delete($brand_id);
	if($result === true){
		$res["status"] = "ok";
		$res["data"] = array("id" => $brand_id);
	}else{
		$res["status"] = "error";
		$res["data"] = "Удалить не удалось";
	}
}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
