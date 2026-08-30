#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("cron_catalog_update_price_table_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(0);
if (function_exists('ini_set')) ini_set('memory_limit','6G');

if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("currency") || !CModule::IncludeModule("catalog")) return;
global $DB;

$limit = 2000;

$update = true;
$filelog = "/userscripts/logs/update_price_table_log.txt";

if(CProSet::getOption("CATALOG_TEMP_TABLE") != "Y" && intval(CProSet::getOption("CATALOG_TEMP_TABLE")) < 100){
	$lastID = CProSet::getOption("CATALOG_TEMP_LAST_ID") - 2001;
	if($lastID < 0) $lastID = 0;
}else{
	$DB->Query("TRUNCATE TABLE ci_price_catalog_tmp", false, $err_mess.__LINE__);
	$lastID = 0;
	CProSet::setOption("CATALOG_TEMP_TABLE", "0");
}

do {
//	$start = debug_microtime_float();
    $prices = [];

	$arSelect = ["ID"];
	$arFilter = [
		"IBLOCK_ID" => CProSet::IB_CATALOG,
		"ACTIVE" => "Y",
//		"!PROPERTY_CML2_ARTICLE" => false,
		">ID" 		=> $lastID,
	];
	$result = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, array("nTopCount" => $limit), $arSelect);
	while ($el = $result->GetNext()) {
		$item = getAllPrice($el["ID"]);
		if (is_array($item) && $item['CML2_ARTICLE']) {
			$prices[$el["ID"]] = $item;
		}

		$lastID = $el["ID"];
	}

	/* добавляем обычные товары */
	if(count($prices) > 0){
		foreach($prices as $product_id => $item){
			//$in = array(

			//	"price_wbby" => (float)$arItem["PRICE_WBBY"],
			//);
			$in = array(
				"product_id" => intval($item["ID"]),
				"product_code" => "'".addslashes($item["CODE"])."'",
				"model" => "'".addslashes($item["CML2_ARTICLE"])."'",
				"detail_page_url" => "'".addslashes($item["DETAIL_PAGE_URL"])."'",
				"price_ru" => (float)$item["PRICE_RU"]["PRICE"],
				"price_discount_ru" => (float)$item["PRICE_RU"]["DISCOUNT_PRICE"],
				"price_by" => (float)$item["PRICE_BY"]["PRICE"],
				"price_discount_by" => (float)$item["PRICE_BY"]["DISCOUNT_PRICE"],
				"price_pl" => (float)$item["PRICE_PL"]["PRICE"],
				"price_discount_pl" => (float)$item["PRICE_PL"]["DISCOUNT_PRICE"],
				"price_ya" => (float)$item["PRICE_YA"]["PRICE"],
				"price_discount_ya" => (float)$item["PRICE_YA"]["DISCOUNT_PRICE"],
				"price_wb" => (float)$item["PRICE_WB"]["PRICE"],
				"price_wbtl" => (float)$item["PRICE_WBTL"]["PRICE"],
				"price_wbby" => (float)$item["PRICE_WBBY"]["PRICE"],
				"price_os" => (float)$item["PRICE_OS"]["PRICE"],
				"price_ozti" => (float)$item["PRICE_OZTI"]["PRICE"],
				"price_sb" => (float)$item["PRICE_SBER"]["PRICE"],
				"price_av" => (float)$item["AVITO_PRICE"]["PRICE"],
				"price_kz" => (float)$item["PRICE_KZ"]["PRICE"],
				"price_ozkz" => (float)$item["PRICE_OZKZ"]["PRICE"],
				//"timestamp" => "'".date("Y-m-d H:i:s")."'",
			);
			//пишем всё во временную таблицу сразу
			$DB->Insert("ci_price_catalog_tmp", $in, $err_mess.__LINE__);
		}
	}

	//die;
	$arFilter = Array(
		"IBLOCK_ID" => CProSet::IB_CATALOG,
		"ACTIVE" => "Y",
//		"!PROPERTY_CML2_ARTICLE" => false,
		"<=ID" => $lastID,
	);
	$rsLeftBorder = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter);
	$leftBorderCnt = $rsLeftBorder->SelectedRowsCount();

	$arFilter = Array(
		"IBLOCK_ID" => CProSet::IB_CATALOG,
		"ACTIVE" => "Y",
//		"!PROPERTY_CML2_ARTICLE" => false,
	);
	$rsAll = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter);
	$allCnt = $rsAll->SelectedRowsCount();

	$p = round(100 * $leftBorderCnt / $allCnt, 2);
	CProSet::setOption("CATALOG_TEMP_TABLE", $p);
	CProSet::setOption("CATALOG_TEMP_LAST_ID", $lastID);
	if($p >= 100) {
		$update = false;
	}
	
} while ($update == true);

if($update === false){
	$arr = [];
	//перезаписываем из временной таблицы в основную
	$strSql = "SELECT * FROM ci_price_catalog_tmp";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arr[] = $row;
	}
	if(count($arr) > 0){
		$DB->Query("TRUNCATE TABLE ci_price_catalog", false, $err_mess.__LINE__);
		foreach($arr as $key => $arItem){
			$in = array(
				"product_id" => intval($arItem["product_id"]),
				"product_code" => "'".addslashes($arItem["product_code"])."'",
				"product_sku" => ($arItem["product_sku"] > 0 ? $arItem["product_sku"] : NULL),
				"model" => "'".addslashes($arItem["model"])."'",
				"detail_page_url" => "'".addslashes($arItem["detail_page_url"])."'",
				"price_by" => (float)$arItem["price_by"],
				"price_discount_by" => (float)$arItem["price_discount_by"],
				"price_ru" => (float)$arItem["price_ru"],
				"price_discount_ru" => (float)$arItem["price_discount_ru"],
				"price_pl" => (float)$arItem["price_pl"],
				"price_discount_pl" => (float)$arItem["price_discount_pl"],
				"price_wb" => (float)$arItem["price_wb"],
				"price_wbtl" => (float)$arItem["price_wbtl"],
				"price_wbby" => (float)$arItem["price_wbby"],
				"price_ya" => (float)$arItem["price_ya"],
				"price_discount_ya" => (float)$arItem["price_discount_ya"],
				"price_os" => (float)$arItem["price_os"],
				"price_av" => (float)$arItem["price_av"],
				"price_sb" => (float)$arItem["price_sb"],
				"price_kz" => (float)$arItem["price_kz"],
				"price_ozkz" => (float)$arItem["price_ozkz"],
			);
			//пишем всё во временную таблицу сразу
			$DB->Insert("ci_price_catalog", $in, $err_mess.__LINE__);
		}
		CProSet::setOption("RUN_CONTROL", "Y");//обновляем значения в кроне update_control_items.php
		CProSet::setOption("CATALOG_TEMP_TABLE", "Y");
	}
}
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
