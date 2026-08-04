<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager')|| $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || 
	!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("catalog")) return;

$res["status"] = "error";
$res["text"] = "";

if($_POST["yandex-shop-add"]) $ar = $_POST["yandex-shop"];
if($_POST["yandex-shop-add"]){
	$tmp = explode("|", $_POST["yandex-shop-add"]);
	foreach($tmp as $shop)
	$ar[] = $shop;
}

//AddMessage2Log($ar);
//die;
CProSet::setOption("YMARKET_HIDE_SHOP", json_encode($ar, JSON_UNESCAPED_UNICODE));
if(is_array($_POST["yandex-shop"]) && count($_POST["yandex-shop"]) > 0){
	global $DB;

	
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
?>