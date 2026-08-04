<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
if(CModule::IncludeModule("panel.manager")){
	
	global $DB;
	
	$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_BRANDS,
	);
	$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
	while($arFields = $result->GetNext()){
		$arBrand[$arFields["ID"]] = $arFields["NAME"];
	}
		
	$strSql = "SELECT el.ID as ID, pr.VALUE as BRAND_ID 
	FROM b_iblock_element el LEFT JOIN b_iblock_element_property pr ON el.ID=pr.IBLOCK_ELEMENT_ID 
	WHERE el.ACTIVE = 'Y' AND el.IBLOCK_ID IN ('16','17') AND pr.IBLOCK_PROPERTY_ID = '87' AND pr.VALUE <> ''";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($arFields = $results->Fetch()){
		$arResult["BRANDS"][$arFields["ID"]] = array(
			"ID" => $arFields["BRAND_ID"],
			"NAME" => $arBrand[$arFields["BRAND_ID"]],
		);
	}
	
	$str_csv = "";
	$strSql = "SELECT * FROM ci_price_catalog";// LIMIT 0,100";// WHERE store_id = '".$DB->ForSql($_REQUEST["store_id"])."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["ITEMS"][] = array(
			"ID" => $row["product_id"],
			"BRAND" => $arResult["BRANDS"][$row["product_id"]]["NAME"],
			"MODEL" => $row["model"],
			"PRICE_BY" => $row["price_by"],
			"PRICE_BY_DISCOUNT" => $row["price_discount_by"],
			"PRICE_RU" => $row["price_ru"],
			"PRICE_RU_DISCOUNT" => $row["price_discount_ru"],
		);
	}
	
	
	$filepath = $_SERVER["DOCUMENT_ROOT"] . "/upload/catalog.csv";
	$fp = fopen($filepath, 'w');

	$arCsv[0] = "MODEL";
	$arCsv[1] = "BRAND";
	$arCsv[2] = "PRICE (BY)";
	$arCsv[3] = "PRICE DISCOUNT (BY)";
	$arCsv[4] = "PRICE (RU)";
	$arCsv[5] = "PRICE DISCOUNT (RU)";
	//prent($arCsv);
	$str_csv = implode(",", $arCsv) . "\r\n";
	file_put_contents($filepath, $str_csv, FILE_APPEND);

	foreach($arResult["ITEMS"] as $key => $arItem){
		$arCsv[0] = $arItem["MODEL"];
		$arCsv[1] = $arItem["BRAND"];
		$arCsv[2] = $arItem["PRICE_BY"];
		$arCsv[3] = $arItem["PRICE_BY_DISCOUNT"];
		$arCsv[4] = $arItem["PRICE_RU"];
		$arCsv[5] = $arItem["PRICE_RU_DISCOUNT"];
		$str_csv = implode(",", $arCsv) . "\r\n";
		file_put_contents($filepath, $str_csv, FILE_APPEND);
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
	//prent($arResult["ITEMS"]);
	//echo $str_csv;
/*	
	$filepath = $_SERVER["DOCUMENT_ROOT"] . "/upload/catalog.csv";
	$fp = fopen($filepath, 'w');
	file_put_contents($filepath , $str_csv, FILE_APPEND);
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
	exit;*/
	?>
	<?
}else{
	?>
	Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');