<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objProduct = new CPanelProduct;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strlen($_POST["an"]) > 3) {
	$result = CPanelProduct::findArticle($_POST["an"]);
	if($result > 0){
		$res["status"] = "ok";
		$res["data"] = "Такая модель уже есть на Tempus.by";
	}else{
		$res["status"] = "error";
	}
}else{
	$res["status"] = "error";
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();

