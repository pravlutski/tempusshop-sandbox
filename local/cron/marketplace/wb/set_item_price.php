#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
// 
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);die;

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');

$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objUtils = new CPanelUtils;
//По крону загружать в свойство "цена WB" минимальную цену ДОСТУПНОГО поставщика умноженную на 3,5. округление до 10
$arResult = array();

$arSettings = array(
	"round" => -1,
	"rate" => 1,
	"currency" => "RUB",
);

$tmp = $objSupplier->getList();
$arCurrency = $objCurrency->getDetail("RUB");

if($arCurrency){
	$arSettings["rate"] = $arCurrency["rate"];
}
	
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE" => "Y",
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","NAME", "PROPERTY_CML2_ARTICLE"));
while($ar = $rs->GetNext()){
	$article = $ar["PROPERTY_CML2_ARTICLE_VALUE"];
	$arResult["ITEMS"][$article] = $ar;
}

if(count($arResult["ITEMS"]) > 0){
	$arPrice = array();
	$filter = array(
		"website" => array("s1"),
		"article" => array_keys($arResult["ITEMS"])
	);
	$price = $objPricelist->getPriceByFilter($filter);
	
	foreach($price as $key => &$arItem){
		//Передавать 0 в ВБ для спецназ, слава, нестеров, Авиатор
		
		if($arItem["model"] && !in_array($arItem["brand_id"], array(60,59,69,66)) && !in_array($arItem["supplier_id"], array(83))){
			
			$arItem["price"] = ($arItem["price"] / $arSettings["rate"]);
			
			$margin = ($arItem["price"] < 800 ? 5 : 3.5);
			
			$arItem["price"] = $arItem["price"] * $margin;
			
			$arItem["price"] = (float)round($arItem["price"], $arSettings["round"]);

			if(isset($arPrice[$arItem["model"]])){
				if($arItem["price"] < $arPrice[$arItem["model"]]["price"])
					$arPrice[$arItem["model"]] = $arItem;
			}else
				$arPrice[$arItem["model"]] = $arItem;
		}
	}
	unset($arItem);
}
foreach($arResult["ITEMS"] as $key => $arItem){
	if($arPrice[$key] && $arPrice[$key]["price"] < 650000){
		CIBlockElement::SetPropertyValuesEx($arItem["ID"], false, array("WBPRICE" => $arPrice[$key]["price"]));
	}else{
		CIBlockElement::SetPropertyValuesEx($arItem["ID"], false, array("WBPRICE" => 0));
	}
}
CProSet::setOption("WB_SET_ITEMS_PRICE", count($arResult["ITEMS"]));

//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>