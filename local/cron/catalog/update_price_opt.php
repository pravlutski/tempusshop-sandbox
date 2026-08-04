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

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");

if(CModule::IncludeModule("panel.manager")){
	global $DB;
	$objPricelist = new CPanelPricelist;
	$objSupplier = new CPanelSupplier;
				
	$tmp = $objSupplier->getList();
				
	$psFilter = array();
	foreach($tmp as $key => $arItem){
		$t = json_decode($arItem["settings_pricelist"], true);
		if($t["opt_price"] == "Y"){
			$psFilter["supplier_id"][] = $arItem["id"];
		}
	}
	if(!$psFilter["supplier_id"]) die("no suplier");
				
	$arSelect = array("model", "MIN(price) as price");
				
	$tmp = $objPricelist->getPriceByFilter($psFilter, true, $arSelect);
	$round = 0;
	$arArticle = array();
				
	foreach($tmp as $key => &$arItem){

		$arItem["price"] = round($arItem["price"], $round);
						//$arItem["price"] = self::rndnum($arItem["price"], ".1");
						//$arItem["price"] = round($arItem["price"] * $round, 0) / $round;
		$arArticle[$arItem["model"]] = $arItem["model"];
		$arResult["ITEMS"][$arItem["model"]] = $arItem;
	}
	unset($arItem);
					
	//$arCatalogPrice = $objPricelist->getCatalogPriceByFilter(array("model" => $arArticle));
	//$arCatalogPrice = $objPricelist->getCatalogPriceByFilter();

	$strSql = "SELECT el.ID as ID, el.IBLOCK_ID as IBLOCK_ID, pr.VALUE as ARTICLE 
	FROM b_iblock_element el 
	LEFT JOIN b_iblock_element_property pr 
	ON el.ID=pr.IBLOCK_ELEMENT_ID 
	WHERE el.ACTIVE = 'Y' AND el.IBLOCK_ID IN ('16','17') AND pr.IBLOCK_PROPERTY_ID IN ('121','123') AND pr.VALUE <> ''";
	
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($arFields = $results->Fetch()){
		if($arFields["IBLOCK_ID"] == 16){
			$arCatalogPrice[] = array(
				"model" => $arFields["ARTICLE"],
				"bitrix_id" => $arFields["ID"],
				"bitrix_sku_id" => 0,
			);
		}else{
			$arCatalogPrice[] = array(
				"model" => $arFields["ARTICLE"],
				"bitrix_id" => 0,
				"bitrix_sku_id" => $arFields["ID"],
			);
		}
		
	}
		
	foreach($arCatalogPrice as $key => $arItem){
		if($arResult["ITEMS"][$arItem["model"]]){
			$arResult["ITEMS_OPT"][$arItem["model"]] = $arResult["ITEMS"][$arItem["model"]];
			$arResult["ITEMS_OPT"][$arItem["model"]]["bitrix_id"] = $arItem["product_id"];
			$arResult["ITEMS_OPT"][$arItem["model"]]["bitrix_sku_id"] = $arItem["product_sku"];
		}else{
			$arResult["ITEMS_OPT"][$arItem["model"]] = array(
				"model" => $arItem["model"],
				"bitrix_id" => $arItem["product_id"],
				"bitrix_sku_id" => $arItem["product_sku"],
				"price" => 0,
			);
		}
	}
//prent($arResult["ITEMS_OPT"]);die;
	foreach($arResult["ITEMS_OPT"] as $key => $arItem){
		if($arItem["bitrix_sku_id"] > 0) continue;//пока пропускаем sku
		
		$arFields = Array(
			"PRODUCT_ID" => $arItem["bitrix_id"],
			"CATALOG_GROUP_ID" => 4,
			"PRICE" => $arItem["price"],
			"CURRENCY" => "RUB",
		);
		$p_res = CPrice::GetList(
			array(),
			array(
				"PRODUCT_ID" => $arItem["bitrix_id"],
				"CATALOG_GROUP_ID" => 4
			)
		);

		if ($arr = $p_res->Fetch()){

			if(round($arr["PRICE"], 0) != $arFields["PRICE"]){
				CPrice::Update($arr["ID"], $arFields);
			}

		}elseif($arFields["PRICE"] > 0){
		
			CPrice::Add($arFields);
			
		}
	}
}
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>