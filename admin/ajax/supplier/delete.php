<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objSupplier = new CPanelSupplier;
global $DB;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$supp_id = intval($_POST["id"]);
	$strSql = "SELECT 1 FROM ci_price WHERE active = 'Y' AND supplier_id = '{$supp_id}'";
	$res1 = $DB->Query( $strSql, false, $err_mess . __LINE__ );
	if ( $res1->SelectedRowsCount() > 0 ){
		$res["status"] = "error";
		$res["data"] = "Нельзя удалить поставщика с активными прайсами";
	}else{
		$result = $objSupplier->delete($supp_id);
		if($result === true){
			$res["status"] = "ok";
			$res["data"] = array("id" => $supp_id);
		}else{
			$res["status"] = "error";
			$res["data"] = "Удалить не удалось";
		}
	}

}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
