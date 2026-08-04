<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule("crm_courier") || !CModule::IncludeModule("panel.manager")) return;

$objCourier = new CCourier();

$res = $APPLICATION->IncludeComponent("adm.courier:order.list", "", array("AJAX" => "Y"), false);

if(($res["COURIER_AVAIL_TODAY_" . $objCourier->siteID] && $res["COURIER_AVAIL_TODAY_" . $objCourier->siteID] >= 0) || ($res["COURIER_AVAIL_TOMORROW_" . $objCourier->siteID] && $res["COURIER_AVAIL_TOMORROW_" . $objCourier->siteID] >= 0)){
	$arResponse["menu"][md5("Доступные")] = "({$res["COURIER_AVAIL_TODAY_" . $objCourier->siteID]}/{$res["COURIER_AVAIL_TOMORROW_" . $objCourier->siteID]})";
}


$res = $APPLICATION->IncludeComponent("adm.courier:order.list.accept", "", array("AJAX" => "Y"), false);

if($res["COURIER_ACCEPT_" . $objCourier->siteID] && $res["COURIER_ACCEPT_" . $objCourier->siteID] >= 0){
	$arResponse["menu"][md5("Активные")] = "({$res["COURIER_ACCEPT_" . $objCourier->siteID]})";
}

$res = $APPLICATION->IncludeComponent("adm.courier:storekeeper.list", "", array("AJAX" => "Y"), false);
if($res["COURIER_STOREKEEPER_" . $objCourier->siteID] && $res["COURIER_STOREKEEPER_" . $objCourier->siteID] >= 0){
	$arResponse["menu"][md5("Распределенные")] = "({$res["COURIER_STOREKEEPER_" . $objCourier->siteID]})";
}

//prent($arResponse);
header('Content-Type: application/json;charset=UTF-8');
echo json_encode($arResponse);


require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');