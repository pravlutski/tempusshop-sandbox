<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager')|| $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || 
	!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("catalog")) return;

$res["status"] = "error";
$res["text"] = "";

if($_POST["onliner-shop-add"]) $ar = $_POST["onliner-shop"];
if($_POST["onliner-shop-add"]){
	$tmp = explode("|", $_POST["onliner-shop-add"]);
	foreach($tmp as $shop)
	$ar[] = $shop;
}

CProSet::setOption("ONLINER_HIDE_SHOP", json_encode($_POST["onliner-shop"], JSON_UNESCAPED_UNICODE));
if(is_array($_POST["onliner-shop"]) && count($_POST["onliner-shop"]) > 0){
	global $DB;

}
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
?>