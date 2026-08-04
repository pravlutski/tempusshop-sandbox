<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;
$objUtils = new CPanelUtils;

$id = intval($_POST['id']);

$objUtils->rmAltBarcodeID($id);
$res = array(
	"status" => ($r ? "ok" : "error"),
	"data" => array(
		'id' => $id
	)
);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
