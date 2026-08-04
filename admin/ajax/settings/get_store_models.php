<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
if(CModule::IncludeModule("panel.manager") && isset($_REQUEST["supplier_id"]) && in_array($_REQUEST["supplier_id"], array("44", "47", "71", "103"))){
	
	global $USER;
	
	$strSql = "SELECT * FROM ci_brands";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arBrand[$row["id"]] = $row["name"];
	}
		
	global $DB;
	
	//$strSql = "SELECT * FROM ci_price WHERE store_id = '".$DB->ForSql($_REQUEST["store_id"])."'";
	$strSql = "SELECT * FROM ci_price WHERE supplier_id = '".$DB->ForSql($_REQUEST["supplier_id"])."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		//$str_csv .= $row["model"] . "\r\n";
		$arCsv[] = array(
			"model" => $row["model"],
			"brand" => $arBrand[$row["brand_id"]],
			"price" => str_replace(".", ",", $row["price"]),
		);
	}


	switch($_REQUEST["supplier_id"]){
		case "44":
			$select = "model, price_by as price, price_discount_by as price_discount";
			break;
		case "44":
			$select = "model, price_ru as price, price_discount_ru as price_discount";
			break;
		case "44":
			$select = "model, price_pl as price, price_discount_pl as price_discount";
			break;
		default:
			$select = "model";
			break;
	}	
	
	$strSql = "SELECT {$select} FROM ci_price_catalog";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arCatalog[$row["model"]] = $row;
	}

	$filepath = $_SERVER["DOCUMENT_ROOT"] . "/upload/simple.csv";
	$fp = fopen($filepath, 'w');
	
	foreach($arCsv as $name => $arItem){
		$ar[0] = $arItem["model"];
		$ar[1] = $arItem["brand"];
		$ar[2] = $arItem["price"];
		$ar[3] = $arCatalog[$arItem["model"]]["price"];
		$ar[4] = $arCatalog[$arItem["model"]]["price_discount"];
		$str_csv = '"' . implode('","', $ar) . '"' . "\r\n";
		file_put_contents($filepath , $str_csv, FILE_APPEND);
	}
	
	if($USER->isAdmin()){
	
	}
	
	fclose($fp);
	ob_end_clean();
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($filepath));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    // читаем файл и отправляем его пользователю
    readfile($filepath);
	exit;
	?>
	<?
}else{
	?>
	Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');