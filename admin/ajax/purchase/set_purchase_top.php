<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;

$items = [];
if ($_POST["items"]) {
	foreach ($_POST["items"] as $item) {
		$items[] = [
			"id" => intval($item["id"]),
			"top_id" => intval($item["top_id"]),
			"product_id" => intval($item["product_id"]),
		];
	}
} else {
	$items[] = [
		"id" => intval($_POST["id"]),
		"top_id" => intval($_POST["top_id"]),
		"product_id" => intval($_POST["product_id"]),
	];
}
/*
$id = intval($_POST["id"]);
$top_id = intval($_POST["top_id"]);
$product_id = intval($_POST["product_id"]);
*/
$website = false;
if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2", "s1_nkz")))
	$website = $_POST["website"];

?>
<?
if(CModule::IncludeModule("panel.manager") && $website && count($items) > 0){
	$objService = new OrderService;
	
	// пока каждый по 1. потом переделать на массив
	foreach ($items as $item ) {
		$ar = false;
		$strSql = "SELECT * FROM ci_price WHERE id = '".$DB->ForSql($item["id"])."' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$ar = $row;
		}
		if($ar){
			$flg = false;
			//$strSql = "SELECT * FROM ci_purchase WHERE model = '".$DB->ForSql($ar["model"])."' AND active = 'Y' AND status = 'T' AND site_id = '".$DB->ForSql($website)."'";
			// смотрим сколько уже добавлено в закупку
			$strSql = "SELECT COUNT(*) as count FROM ci_purchase WHERE product_id = '".$item["product_id"]."' AND active = 'Y' AND status = 'T' AND site_id = '".$DB->ForSql($website)."'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			$countBuy = $countNeed = 0;
			if ($row = $results->Fetch()){
				$countBuy = $row["count"];
			}
			// смотрим сколько надо закупить
			$strSql = "SELECT * FROM ci_top_models WHERE site_id = '".$DB->ForSql($website)."' AND bitrix_id = '{$item["product_id"]}'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$countNeed = ceil(($row["sell_quantity"] / (365 / 6)));
			}
			//prent($countNeed);
			if($countNeed == 0 || $countBuy >= $countNeed){
				$flg = true;
			}
			if($flg === false){

				$in = array(
					"active" => "'Y'",
					"site_id" => "'".$website."'",
					"model" => "'".addslashes($ar["model"])."'",
					//"top_id" => "'".$top_id."'",
					"user_id" => $USER->getID(),
					"price" => "'".$ar["price"]."'",
					"status" => "'T'",
					"supp_id" => $ar["supplier_id"],
					"product_id" => $item["product_id"],
				);
				$ID = $DB->Insert("ci_purchase", $in, $err_mess.__LINE__);
				$res = array(
					'status' => ($ID > 0 ? "ok" : "error"),
					'text' => ($ID > 0 ? "Запись добавлена" : "Не удалось сохранить"),
				);
			}else{
				$res = array(
					'status' => "error",
					'text' => "Закупки по данному товару уже созданы"
				);
			}
		}else{
			$res = array(
				'status' => "error",
				'text' => "Не найдена запись в прайслисте. Обновите страницу."
			);
		}
	}


}else{
	$res = array(
		'status' => 'error',
		'text' => "Не удалось сохранить. Не корректные данные"
	);
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
?>