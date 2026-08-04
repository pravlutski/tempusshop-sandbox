<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objSupplier = new CPanelSupplier;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	//AddMessage2Log($_POST);
	
	if ($_POST['website'] == 'wbtl') {
		CProSet::setOption("CATALOG_SALE_wbtl", $_POST['catalog_sale']);
		CProSet::setOption("CATALOG_PROMO_wbtl", $_POST['catalog_promo']);
	} elseif($_POST['website'] == 'wb') {
		CProSet::setOption("CATALOG_SALE_wb", $_POST['catalog_sale']);
		CProSet::setOption("CATALOG_PROMO_wb", $_POST['catalog_promo']);
	}

	$res["status"] = "ok";
	$res["data"] = "ok";
}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}


echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
