<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objContent = new CPanelContent;
if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
	$result = $objContent->returnTask($_POST["id"]);
	if($result === "ok"){
		$res["status"] = "ok";
		$res["data"] = "Элемент вернули в контект-редактор";
	}elseif($result == "exists"){
		$res["status"] = "ok";
		$res["data"] = "Элемент уже возвращен в контект-редактор";
	}else{
		$res["status"] = "error";
		$res["data"] = "Вернуть не удалось";
	}
}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();

