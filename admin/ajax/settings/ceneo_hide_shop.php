<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager')|| $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || 
	!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("catalog")) return;

$res["status"] = "error";
$res["text"] = "";

if($_POST["ceneo-shop-add"]) $ar = $_POST["ceneo-shop"];
if($_POST["ceneo-shop-add"]){
	$tmp = explode("|", $_POST["ceneo-shop-add"]);
	foreach($tmp as $shop)
	$ar[] = $shop;
}

CProSet::setOption("CENEO_HIDE_SHOP", json_encode($_POST["ceneo-shop"], JSON_UNESCAPED_UNICODE));
if(is_array($_POST["ceneo-shop"]) && count($_POST["ceneo-shop"]) > 0){
	global $DB;

}
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
?>