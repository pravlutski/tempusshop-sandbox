<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objBrand = new CPanelBrand;
$brand = $objBrand->getList();

$res["status"] = (count($brand) > 0 ? "ok" : "error");

$service = PanelManager::getPriceManager();
$typePrices = $service->getTypePrices();
	
$res["data"] = array(
	'brand' => $brand,
	'typePrices' => $typePrices,
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
