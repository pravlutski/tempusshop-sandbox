<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objPricelist = new CPanelPricelist;

$id = intval($_POST["id"]);
$status = $_POST["status"];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST["id"] > 0 && in_array($status, array("N", "Y"))) {
	if($objPricelist->changeActivity($id, $status)){
		$res["status"] = "ok";
		CProSet::setOption("UPDATE_CATALOG", "Y");
		system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_catalog_all.php >/dev/null 2>&1 &");
	}else{
		$res["status"] = "error";
		$res["text"] = "Удалить не удалось";
	}
/*	$result = $objPricelist->delete( $_POST["id"] );
	if($result === true){
		$res["status"] = "ok";
		$res["data"] = array("id" => $_POST["id"]);
		CProSet::getOption("UPDATE_CATALOG", "Y");
	}else{
		$res["status"] = "error";
		$res["data"] = "Удалить не удалось";
	}*/
}else{
	$res["status"] = "error";
	$res["text"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
