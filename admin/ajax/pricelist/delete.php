<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;
global $DB;
$objPricelist = new CPanelPricelist;
global $USER;
$arGroups = $USER->GetUserGroupArray();
if(!$USER->isAdmin() && !in_array(6, $arGroups)){
	$res["status"] = "error";
	$res["data"] = "Доступ запрещен";
	echo json_encode($res, JSON_UNESCAPED_UNICODE);
	header('Content-Type: application/json;charset=UTF-8');
	die();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	//обновляем даты когда пропали модели
	$rs = $objPricelist->getPricelistDetail($_POST["id"]);
	if( $rs ){
		$price = $objPricelist->getPriceByFilter(array("supplier_id" => $rs["supplier_id"], "brand_id" => $rs["brand_id"]));
		foreach($price as $key => $arItem)
			$objPricelist->updateDateDisappear($arItem["model"]);
	}

	$result = $objPricelist->delete( $_POST["id"] );
	if($result === true){
		$res["status"] = "ok";
		$res["data"] = array("id" => $_POST["id"]);
		
		CProSet::setOption("UPDATE_CATALOG", "Y");
		system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_catalog_all.php >/dev/null 2>&1 &");
		
		CExchange::forceYmarket("s1");
		CExchange::forceYmarket("s2");
	}else{
		$res["status"] = "error";
		$res["data"] = "Удалить не удалось";
	}
}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
