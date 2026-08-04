<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;
$in = array(
	"id" => intval($_POST["brand-id"]),
	"name" => addslashes($_POST["name"]),
	"alt_name" => addslashes($_POST["alt_name"]),
	//"regular" => addslashes($_POST["regular"]),
	"regular" => ($_POST["regular"]),
	"sort" => intval($_POST["sort"]),
	"bitrix_id" => intval($_POST["bitrix_id"]),
	"margin_ru" => $_POST["margin_ru"],
	"margin_by" => $_POST["margin_by"],
	"margin_pl" => $_POST["margin_pl"],
	"margin_ya" => $_POST["margin_ya"],
	"margin_os" => $_POST["margin_os"],
	"margin_wb" => $_POST["margin_wb"],
	"regular_search" => ($_POST["regular_search"]),
	"regular_replace" => ($_POST["regular_replace"]),
);
$id = intval($_POST["brand-id"]);
$brand = new CPanelBrand;
$result = $brand->apply($in);

if($result === true){
	$res["status"] = "ok";
	$res["data"] = "ok";
}elseif(is_int($result)){
	$res["status"] = "ok";
	$res["data"] = "new";
}else{
	$res["status"] = "error";
	$res["data"] = "Сохранить не удалось";
}
	
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
