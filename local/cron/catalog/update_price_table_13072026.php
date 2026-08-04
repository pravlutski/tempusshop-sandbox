#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);
if (function_exists('ini_set')) ini_set('memory_limit','6G');

if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("currency") || !CModule::IncludeModule("catalog")) return;
global $DB;

$limit = 800;

$update = true;
$filelog = "/userscripts/logs/update_price_table_log.txt";

if(CProSet::getOption("CATALOG_TEMP_TABLE") != "Y" && intval(CProSet::getOption("CATALOG_TEMP_TABLE")) < 100){
	$lastID = CProSet::getOption("CATALOG_TEMP_LAST_ID") - 801;
	if($lastID < 0) $lastID = 0;
}else{
	$DB->Query("TRUNCATE TABLE ci_price_catalog_tmp", false, $err_mess.__LINE__);
	$lastID = 0;
	CProSet::setOption("CATALOG_TEMP_TABLE", "0");
}

do {
//	$start = debug_microtime_float();

	$productList = array();
	$arResultPricesRu = CIBlockPriceTools::GetCatalogPrices(CProSet::IB_CATALOG, array("BASE_SITE"));
	$arResultPricesBy = CIBlockPriceTools::GetCatalogPrices(CProSet::IB_CATALOG, array("BASE_BEL"));
	$arResultPricesPl = CIBlockPriceTools::GetCatalogPrices(CProSet::IB_CATALOG, array("BASE_PL"));
	$arResultPricesV1 = CIBlockPriceTools::GetCatalogPrices(CProSet::IB_CATALOG, array("BASE"));

    $arTmpOffers = $arTmp = [];

	$arSelect = Array(
		"ID", "CODE", "DETAIL_PAGE_URL", 
		"PROPERTY_CML2_ARTICLE", "CATALOG_GROUP_1", "CATALOG_GROUP_2", 
		"CATALOG_GROUP_3", "CATALOG_GROUP_5", "PROPERTY_WBPRICE", "PROPERTY_OZSB_PRICE",
		"PROPERTY_AVITO_PRICE", "PROPERTY_SBER_PRICE", 
		"PROPERTY_PRICE_KZ", "PROPERTY_PRICE_OZKZ", "PROPERTY_WBTL_PRICE"
	);
	$arFilter = Array(
		"IBLOCK_ID" => CProSet::IB_CATALOG,
		"ACTIVE" => "Y",
//		"!PROPERTY_CML2_ARTICLE" => false,
		">ID" 		=> $lastID,
	);
	$result = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, array("nTopCount" => $limit), $arSelect);
	while ($el = $result->GetNext()){
		if($el["PROPERTY_CML2_ARTICLE_VALUE"]){
			$arTmp[$el["ID"]]["NAME"] = $el["PROPERTY_CML2_ARTICLE_VALUE"];
			$arTmp[$el["ID"]]["CODE"] = $el["CODE"];
			$arTmp[$el["ID"]]["DETAIL_PAGE_URL"] = $el["DETAIL_PAGE_URL"];

			$arPrice1 = CIBlockPriceTools::GetItemPrices(CProSet::IB_CATALOG, $arResultPricesRu, $el, "N", array(), "", "s1");
			$arPrice2 = CIBlockPriceTools::GetItemPrices(CProSet::IB_CATALOG, $arResultPricesBy, $el, "N", array(), "", "s2");
			$arPrice3 = CIBlockPriceTools::GetItemPrices(CProSet::IB_CATALOG, $arResultPricesPl, $el, "N", array(), "", "s3");
			$arPrice5 = CIBlockPriceTools::GetItemPrices(CProSet::IB_CATALOG, $arResultPricesV1, $el, "N", array(), "", "s1");

			$arTmp[$el["ID"]]["PRICE1"] = array(
				"PRICE" => $arPrice1["BASE_SITE"]["VALUE"],
				"DISCOUNT_PRICE" => $arPrice1["BASE_SITE"]["DISCOUNT_VALUE"],
			);
			$arTmp[$el["ID"]]["PRICE2"] = array(
				"PRICE" => $arPrice2["BASE_BEL"]["VALUE"],
				"DISCOUNT_PRICE" => $arPrice2["BASE_BEL"]["DISCOUNT_VALUE"],
			);
			$arTmp[$el["ID"]]["PRICE3"] = array(
				"PRICE" => $arPrice3["BASE_PL"]["VALUE"],
				"DISCOUNT_PRICE" => $arPrice3["BASE_PL"]["DISCOUNT_VALUE"],
			);

			$arTmp[$el["ID"]]["PRICE5"] = array(
				"PRICE" => $arPrice5["BASE"]["VALUE"],
				"DISCOUNT_PRICE" => $arPrice5["BASE"]["DISCOUNT_VALUE"],
			);

			$arTmp[$el["ID"]]["PRICE_WB"] = $el["PROPERTY_WBPRICE_VALUE"];
			$arTmp[$el["ID"]]["PRICE_OZON"] = $el["PROPERTY_OZSB_PRICE_VALUE"];
			$arTmp[$el["ID"]]["PRICE_AV"] = $el["PROPERTY_AVITO_PRICE_VALUE"];
			$arTmp[$el["ID"]]["PRICE_SB"] = $el["PROPERTY_SBER_PRICE_VALUE"];
			$arTmp[$el["ID"]]["PRICE_KZ"] = $el["PROPERTY_PRICE_KZ_VALUE"];
			$arTmp[$el["ID"]]["PRICE_OZKZ"] = $el["PROPERTY_PRICE_OZKZ_VALUE"];
			$arTmp[$el["ID"]]["PRICE_WBTL"] = $el["PROPERTY_WBTL_PRICE_VALUE"];
		}

		if($el["IBLOCK_ID"] == 17)
			$productList[] = $el["ID"];

		$lastID = $el["ID"];
	}
	/* торговыен предложения */

//	file_put_contents($filelog, "STEP 1 - " . (debug_microtime_float() - $start) . "\r\n", FILE_APPEND);

	if (count($productList) > 0) {

		$arInfo = CCatalogSKU::GetInfoByProductIBlock(CProSet::IB_CATALOG);
		$arResultPricesRu = CIBlockPriceTools::GetCatalogPrices($arInfo['IBLOCK_ID'], array("BASE"));
		$arResultPricesBy = CIBlockPriceTools::GetCatalogPrices($arInfo['IBLOCK_ID'], array("BASE_BEL"));
		$arResultPricesPl = CIBlockPriceTools::GetCatalogPrices($arInfo['IBLOCK_ID'], array("BASE_PL"));

		$offersExist = CCatalogSKU::getExistOffers($productList);
		$arID = array();
		foreach($offersExist as $product_id => $isExist){
			if($isExist == true){
				$arID[] = $product_id;
			}
		}
	//echo serialize($offersExist);die;
		if(count($arID) > 0){
			$arSelect = Array("ID", "CODE", "DETAIL_PAGE_URL", "PROPERTY_ARTICLE", "PROPERTY_" . $arInfo["SKU_PROPERTY_ID"], "CATALOG_GROUP_1", "CATALOG_GROUP_2", "CATALOG_GROUP_3");
			$arFilter = Array(
				"IBLOCK_ID" => $arInfo['IBLOCK_ID'],
				"ACTIVE" => "Y",
				"!PROPERTY_ARTICLE" => false,
				'PROPERTY_'.$arInfo['SKU_PROPERTY_ID'] => $arID,//$product_id,
			);

			$rsOffers = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
			while ($arOffer = $rsOffers->GetNext()) {

				$arTmpOffers[$arOffer["ID"]]["NAME"] = $arOffer["PROPERTY_ARTICLE_VALUE"];
				$arTmpOffers[$arOffer["ID"]]["CODE"] = $arOffer["CODE"];
				$arTmpOffers[$arOffer["ID"]]["PRODUCT_ID"] = $arOffer["PROPERTY_" . $arInfo["SKU_PROPERTY_ID"] . "_VALUE"];
				$arTmpOffers[$arOffer["ID"]]["DETAIL_PAGE_URL"] = $arOffer["DETAIL_PAGE_URL"];

				$arPrice1 = CIBlockPriceTools::GetItemPrices($arInfo['IBLOCK_ID'], $arResultPricesRu, $arOffer, "N", array(), "", "s1");
				$arPrice2 = CIBlockPriceTools::GetItemPrices($arInfo['IBLOCK_ID'], $arResultPricesBy, $arOffer, "N", array(), "", "s2");
				$arPrice3 = CIBlockPriceTools::GetItemPrices($arInfo['IBLOCK_ID'], $arResultPricesPl, $arOffer, "N", array(), "", "s3");

				$arTmpOffers[$arOffer["ID"]]["PRICE1"] = array(
					"PRICE" => $arPrice1["BASE"]["VALUE"],
					"DISCOUNT_PRICE" => $arPrice1["BASE"]["DISCOUNT_VALUE"],
				);
				$arTmpOffers[$arOffer["ID"]]["PRICE2"] = array(
					"PRICE" => $arPrice2["BASE_BEL"]["VALUE"],
					"DISCOUNT_PRICE" => $arPrice2["BASE_BEL"]["DISCOUNT_VALUE"],
				);
				$arTmpOffers[$arOffer["ID"]]["PRICE3"] = array(
					"PRICE" => $arPrice3["BASE_PL"]["VALUE"],
					"DISCOUNT_PRICE" => $arPrice3["BASE_PL"]["DISCOUNT_VALUE"],
				);
			}
		}
		foreach($arTmpOffers as $key => $arItem){
			unset($arTmp[$arItem["PRODUCT_ID"]]);
		}
	}

//	file_put_contents($filelog, "STEP 2 - " . (debug_microtime_float() - $start) . "\r\n", FILE_APPEND);
	/* добавляем sku */
	if(count($arTmpOffers) > 0){
		foreach($arTmpOffers as $sku_id => $arItem){
			$in = array(
				"product_id" => intval($arItem["PRODUCT_ID"]),
				"product_code" => "'".addslashes($arItem["CODE"])."'",
				"product_sku" => intval($sku_id),
				"model" => "'".addslashes($arItem["NAME"])."'",
				"detail_page_url" => "'".addslashes($arItem["DETAIL_PAGE_URL"])."'",
				"price_by" => (float)$arItem["PRICE2"]["PRICE"],
				"price_discount_by" => (float)$arItem["PRICE2"]["DISCOUNT_PRICE"],
				"price_ru" => (float)$arItem["PRICE1"]["PRICE"],
				"price_discount_ru" => (float)$arItem["PRICE1"]["DISCOUNT_PRICE"],
				"price_pl" => (float)$arItem["PRICE3"]["PRICE"],
				"price_discount_pl" => (float)$arItem["PRICE3"]["DISCOUNT_PRICE"],
			);
			//пишем всё во временную таблицу сразу
			$DB->Insert("ci_price_catalog_tmp", $in, $err_mess.__LINE__);
		}
	}
//	file_put_contents($filelog, "STEP 3 - " . (debug_microtime_float() - $start) . "\r\n", FILE_APPEND);
	/* добавляем обычные товары */
	if(count($arTmp) > 0){
		foreach($arTmp as $product_id => $arItem){
			$in = array(
				"product_id" => intval($product_id),
				"product_code" => "'".addslashes($arItem["CODE"])."'",
				"model" => "'".addslashes($arItem["NAME"])."'",
				"detail_page_url" => "'".addslashes($arItem["DETAIL_PAGE_URL"])."'",
				"price_by" => (float)$arItem["PRICE2"]["PRICE"],
				"price_discount_by" => (float)$arItem["PRICE2"]["DISCOUNT_PRICE"],
				"price_ru" => (float)$arItem["PRICE1"]["PRICE"],
				"price_discount_ru" => (float)$arItem["PRICE1"]["DISCOUNT_PRICE"],
				"price_pl" => (float)$arItem["PRICE3"]["PRICE"],
				"price_discount_pl" => (float)$arItem["PRICE3"]["DISCOUNT_PRICE"],
				"price_wb" => (float)$arItem["PRICE_WB"],
				"price_wbtl" => (float)$arItem["PRICE_WBTL"],
				"price_wbby" => (float)$arItem["PRICE_WBBY"],
				"price_ya" => (float)$arItem["PRICE5"]["PRICE"],
				"price_discount_ya" => (float)$arItem["PRICE5"]["DISCOUNT_PRICE"],
				"price_os" => (float)$arItem["PRICE_OZON"],
				"price_av" => (float)$arItem["PRICE_AV"],
				"price_sb" => (float)$arItem["PRICE_SB"],
				"price_kz" => (float)$arItem["PRICE_KZ"],
				"price_ozkz" => (float)$arItem["PRICE_OZKZ"],
			);
			//пишем всё во временную таблицу сразу
			$DB->Insert("ci_price_catalog_tmp", $in, $err_mess.__LINE__);
		}
	}

//	file_put_contents($filelog, "STEP 4 - " . (debug_microtime_float() - $start) . "\r\n", FILE_APPEND);
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
//	file_put_contents($filelog, "STEP 5 - " . (debug_microtime_float() - $start) . "\r\n", FILE_APPEND);
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
