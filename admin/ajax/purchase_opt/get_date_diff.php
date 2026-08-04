<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;
$res["period"] = $_POST["period"];
if($_POST["period"] == "year"){
	$res = array(
		"DATE_FROM" => date("Y-m-d", strtotime("-1 year")),
		"DATE_TO" => date("Y-m-d"),
	);
}else{
	$res = array(
		"DATE_FROM" => date("Y-m-d", strtotime("-1 month")),
		"DATE_TO" => date("Y-m-d"),
	);
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
