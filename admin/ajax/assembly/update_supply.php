<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;
if(!Loader::includeModule('maxyss.wb'))return;

include_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/components/adm/order.assembly/wbtools.php");

if(empty($_SESSION["CABINET"])) {
	$_SESSION["CABINET"] = "WR";
}
$cabinet_init = $_SESSION["CABINET"];
$wb = new WBTools($cabinet_init);

$supplyId = trim(htmlspecialchars($_REQUEST["supplyId"]));

if(strlen($supplyId) > 0 && is_array($_REQUEST["order"]) && count($_REQUEST["order"]) > 0){
	$arError = $arOrder = [];
	foreach($_REQUEST["order"] as $orderId){
		$arOrder[] = intval(trim(htmlspecialchars($orderId)));
	}
	$resWB = $wb->addListOrderToSupplie($supplyId, $arOrder);

	$arLog = [
		"date" => date("Y-m-d H:i:s"),
		"_REQUEST" => $_REQUEST,
		"resWB" => $resWB,
	];

	file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/bitrix/components/adm/order.assembly/update_supply.txt", print_r($arLog, true), 8);
	/*foreach($_REQUEST["order"] as $orderId){
		$orderId = trim(htmlspecialchars($orderId));
		if(strlen($orderId) > 0){
			$resWB = $wb->addOrderToSupplie($orderId, $supplyId);
			//$resWB = [];
			if($resWB["success"] != true){
				$arError[] = "<span class='label label-danger' style='display: block;'>" . ($resWB["error"] ? $resWB["error"] : "{$orderId} не добавлен в поставку {$supplyId}"). "</span>";
			}
		}else{
			$arError[] =  "<span class='label label-danger' style='display: block;'>" . ($resWB["error"] ? $resWB["error"] : "Пустой ID заказа"). "</span>";
		}
	}*/
}else{
	$arError[] = "<span class='label label-danger' style='display: block;'>Некорректный запрос</span>";
}

$res = array(
	'status' => (count($arError) > 0 ? "error" : "ok"),
	'data' => implode("", $arError)
);
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
