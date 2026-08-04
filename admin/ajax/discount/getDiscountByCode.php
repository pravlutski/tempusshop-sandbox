<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$id = (int)$_POST["id"];
$objDiscount = new CPanelDiscount;

if( $id = $objDiscount->getID(str_replace(' ', '', $_POST["text"])) ){
	$sum = $objDiscount->getDiscount( $id );
	$res["status"] = "ok";
	$res["data"] = array('discount'=>$sum);
}else{
	$res["status"] = "error";
	$res["data"] = "Нет такой карты";
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
