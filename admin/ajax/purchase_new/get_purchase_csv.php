<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;

$arGroups = CUser::GetUserGroup($USER->GetID());
if(CModule::IncludeModule("panel.manager") && ($USER->isAdmin() || in_array(6, $arGroups) || in_array(12, $arGroups) || in_array(13, $arGroups))):?>
<?require_once '/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';?>
<?

$objSupplier = new CPanelSupplier;

$objCurrency = new CPanelCurrency;



$supp_id = intval($_REQUEST["supp_id"]);

$add_where = false;
if($supp_id > 0){
	$add_where[] = "supp_id = '".$DB->ForSql($supp_id)."'";

	$arSupp = $objSupplier->getDetail($supp_id);
	$arSupp["settings"] = json_decode($arSupp["settings"], true);
	$arSupp["settings_pricelist"] = json_decode($arSupp["settings_pricelist"], true);

	if($arSupp["settings"]["currency_list"] && $arSupp["settings"]["currency_list"] != "RUB"){
		$arCurrency = $objCurrency->getDetail($arSupp["settings"]["currency_list"]);
		$currency = ($arCurrency["rate"] > 0 ? $arCurrency["rate"] : false);
	}


}
// && in_array($_REQUEST["site_id"], array("s1", "s2", "s3"))
if(isset($_REQUEST["site_id"])){
	$add_where[] = "site_id IN ('".implode("','", $_REQUEST["site_id"])."')";
}
//
if(isset($_REQUEST["currency_list"])){
	$arList = [];
	foreach($objSupplier->getList() as $k => $arItem){
		$settings = json_decode($arItem["settings"], true);

		//if($settings["currency_list"] && in_array($settings["currency_list"], $_REQUEST["currency_list"])){
		if($settings["currency_list"] && $settings["currency_list"] == $_REQUEST["currency_list"]){
			$arList[] = $arItem["id"];
		}
	}

	if(is_array($arList) && count($arList) > 0)
		$add_where[] = "supp_id IN ('".implode("','",$arList)."')";
	//prent($add_where);die;

	if($_REQUEST["currency_list"] != "RUB"){
		$arCurrency = $objCurrency->getDetail($_REQUEST["currency_list"]);
		$currency = ($arCurrency["rate"] > 0 ? $arCurrency["rate"] : false);
	}
}

$add_where[] = "active = 'Y'";

if(isset($_REQUEST["productID"]) && strlen($_REQUEST["productID"]) > 0){
	$arProductID = explode(",", addslashes($_REQUEST["productID"]));
	$arProductID = array_diff($arProductID, array(''));

	if(is_array($arProductID) && count($arProductID) > 0)
		$add_where[] = "product_id IN ('".implode("','", $arProductID)."')";

}
if(isset($_REQUEST["purchaseID"]) && strlen($_REQUEST["purchaseID"]) > 0){

	$arPurchaseID = explode(",", addslashes($_REQUEST["purchaseID"]));
	$arPurchaseID = array_diff($arPurchaseID, array(''));

	if(is_array($arPurchaseID) && count($arPurchaseID) > 0)
		$add_where[] = "id IN ('".implode("','", $arPurchaseID)."')";
}

global $USER;


/*
if($USER->isAdmin()){
	prent($arSupp);
	prent($arCurrency);
	die;
}*/
//
?>
<?
	//prent($add_where);


	if($add_where && count($add_where) > 0){
		$add_where = implode(" AND ",$add_where);
	}


	$strSql = "SELECT * FROM ci_purchase WHERE " . $add_where;

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$ar[$row["id"]] = $row;
		if($row["product_id"]){
			$arIDs[$row["product_id"]] = $row["product_id"];
		}
	}
	//prent($add_where);die;
	$arResult["SUPPLIER_LIST"] = $objSupplier->getList();
	foreach($arResult["SUPPLIER_LIST"] as $arSup){
		$arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
	}
	foreach($ar as $key => $arItem){
		//$arGroup[$arItem["supp_id"]][$key] = $arItem;
		$arGroup[$arItem["supp_id"]][$arItem["product_id"]][] = $arItem;
	}
	//prent($arGroup);die;

	//вытягиваем названия из битрикса
	//$arIDs[] = 3016;
	if(is_array($arIDs) && count($arIDs) > 0){
		$res = CIBlockElement::GetList(array(), array("ID" => $arIDs), array("ID", "NAME"));
		while($ar_fields = $res->GetNext()){
			$arBitrix[$ar_fields["ID"]] = $ar_fields["NAME"];
		}
	}
	//prent($arGroup);prent($arBitrix);die;
	$filepath = $_SERVER["DOCUMENT_ROOT"] . "/upload/get_purchase.xlsx";
	// $fp = fopen($filepath, 'w');
	//
	// $arCsv[0] = iconv("UTF-8", "WINDOWS-1251", "Наименование");
	// $arCsv[1] = iconv("UTF-8", "WINDOWS-1251", "Количество");
	// //$arCsv[2] = iconv("UTF-8", "WINDOWS-1251", "Цена");
	// $str_csv = implode(";", $arCsv) . "\r\n";
	// file_put_contents($filepath , $str_csv, FILE_APPEND);

		//prent($arGroup);die;

		// foreach($arGroup as $supp_id => $arItems){
		// 	$sup_name = iconv("UTF-8", "WINDOWS-1251", $arResult["SUPPLIER_NAME"][$supp_id]);
		// 	$sup_name = '"' . $sup_name . '"';
		// 	file_put_contents($filepath , $sup_name . "\r\n", FILE_APPEND);
		//
		//
		// 	foreach($arItems as $product_id => $arr){
		//
		// 		foreach($arr as $k => $arItem){
		//
		// 			if($k > 0) continue;
		//
		// 			$price = $arItem["price"];
		//
		// 			if($currency){
		// 				$price = $price / $currency;
		// 				$price = round($price, 2);
		// 			}
		//
		// 			$name = ($arBitrix[$arItem["product_id"]] ? $arBitrix[$arItem["product_id"]] : $arItem["model"]);
		// 			$arCsv[0] = iconv("UTF-8", "WINDOWS-1251", $name);
		// 			$arCsv[1] = count($arr);
		// 			//$arCsv[2] = str_replace(".", ",", $price);
		// 			$str_csv = '"'.implode('";"', $arCsv) . '"' . "\r\n";
		// 			file_put_contents($filepath , $str_csv, FILE_APPEND);
		//
		// 		}
		//
		// 	}
		// 	file_put_contents($filepath , "\r\n", FILE_APPEND);
		// }

		$objPHPExcel = new PHPExcel();
		$objPHPExcel->setActiveSheetIndex(0);
		$sheet = $objPHPExcel->getActiveSheet();
		$title = 'Закупки';
		//print_r($title);
		//die();
		$sheet->setTitle($title);
		$sheet->getColumnDimension("A")->setWidth(30);
		$sheet->getColumnDimension("B")->setWidth(30);
		$sheet->getColumnDimension("C")->setWidth(30);
		$sheet->setCellValue("A1", "Модель");
		$sheet->getStyle("A1")->getFont()->setBold(true);
		$sheet->setCellValue("B1", "Кол-во");
		$sheet->getStyle("B1")->getFont()->setBold(true);
		$sheet->setCellValue("C1", "Цена");
		$sheet->getStyle("C1")->getFont()->setBold(true);
		$row = 2;
		foreach($arGroup as $supp_id => $arItems){
			// $name = explode('(',$arResult["SUPPLIER_NAME"][$supp_id]);
			// $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name[0]);
			// $name = $name[0];
			// $invalidCharacters = $sheet->getInvalidCharacters();
			// $name = str_replace($invalidCharacters, '', $name);

			$sup_name = $arResult["SUPPLIER_NAME"][$supp_id];

			$column = 0;

			$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column, $row, $sup_name);
			$row++;

			foreach($arItems as $product_id => $arr){
				// print_r($arItems);
				// die();
				foreach($arr as $k => $arItem){

					if($k > 0) continue;

					$price = $arItem["price"];

					if($currency){
						$price = $price / $currency;
						$price = round($price, 2);
					}

					$name = ($arBitrix[$arItem["product_id"]] ? $arBitrix[$arItem["product_id"]] : $arItem["model"]);

					$column = 0;
					$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column, $row, $name);
					$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+1, $row, count($arr));
					//if ($supp_id == 103) {
						$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+2, $row, $price);
					//}
					$row++;
				}
			}
			$row = $row+3;

		}
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		$objWriter->save($filepath);
/*
	$arCsv[0] = iconv("UTF-8", "WINDOWS-1251", "Наименование");
	$arCsv[1] = iconv("UTF-8", "WINDOWS-1251", "Закупочная цена");
	$str_csv = implode(";", $arCsv) . "\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);


	foreach($arGroup as $supp_id => $arItems){
		$sup_name = iconv("UTF-8", "WINDOWS-1251", $arResult["SUPPLIER_NAME"][$supp_id]);
		$sup_name = '"' . $sup_name . '"';
		file_put_contents($filepath , $sup_name . "\r\n", FILE_APPEND);
		foreach($arItems as $arItem){
			$arCsv[0] = ($arBitrix[$arItem["product_id"]] ? $arBitrix[$arItem["product_id"]] : $arItem["model"]);
			$arCsv[0] = iconv("UTF-8", "WINDOWS-1251", $arCsv[0]);
			$arCsv[1] = str_replace(".", ",", $arItem["price"]);
			$str_csv = '"'.implode('";"', $arCsv) . '"' . "\r\n";
			file_put_contents($filepath , $str_csv, FILE_APPEND);
		}
		file_put_contents($filepath , "\r\n\r\n\r\n", FILE_APPEND);
	}
	*/

	file_force_download($filepath);

?>
<?endif?>
<?die;?>
