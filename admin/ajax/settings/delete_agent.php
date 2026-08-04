<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objAgent = new CPanelEmployee;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id = intval($_POST["id"]);
	$result = $objAgent->delete($id);
	if($result === true){
		$res["status"] = "ok";
		$res["text"] = "Представитель {$id} удален.";
	}else{
		$res["status"] = "error";
		$res["text"] = "Удалить не удалось";
	}
}else{
	$res["status"] = "error";
	$res["text"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
