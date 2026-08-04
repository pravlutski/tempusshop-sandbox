<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;
$objUtils = new CPanelUtils;
$an = trim( $_POST['an'] );
$alt = trim( $_POST['alt'] );

$objUtils->rmAltAn( $an, $alt );
$res = array(
	"status" => ($r ? "ok" : "error"),
	"data" => array(
		'an' => $an,
		'alt' => $alt
	)
);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
