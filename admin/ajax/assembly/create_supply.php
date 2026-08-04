<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;
if(!Loader::includeModule('maxyss.wb'))return;

include_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/components/adm/order.assembly/wbtools.php");
if(empty($_SESSION["CABINET"])) {
	$_SESSION["CABINET"] = "WR";
}
$cabinet_init = $_SESSION["CABINET"];
$wb = new WBTools($cabinet_init);

$name = trim(htmlspecialchars($_REQUEST["name"]));

if(strlen($name) >= 1){
	$result = $wb->addSupplies($name);

	$res = array(
		'status' => ($result["error"] == false && strlen($result["supplyId"]) > 0 ? "ok" : "error"),
		'data' => serialize($result)
	);
}else{
	$res = array(
		'status' => "error",
		'data' => "<span class='label label-danger' style='display: block;'>Некорректное имя</span>"
	);
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
