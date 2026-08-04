#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
// 
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');

$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objUtils = new CPanelUtils;

$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_BRANDS,
);
$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
while($arFields = $result->GetNext()){
	$arBrand[$arFields["ID"]] = ($arBrandReplace[$arFields["NAME"]] ? $arBrandReplace[$arFields["NAME"]] : $arFields["NAME"]);
}

//По крону записывать поле "Символьный код" в поле "Артикул WB"
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
//	"PROPERTY_WBARTICLE" => false,
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","CODE","PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND"));
while($ob = $rs->GetNextElement()){
	
	$arFields = $ob->GetFields();
	
	$code = $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
	
	
	$brand = $arBrand[$arFields["PROPERTY_BRAND_VALUE"]];
	
	//T_Casio_GA-100-1A1
	if(strlen($brand . "_" . $code) <= 34){
		$code = "T_" . $brand . "_" . $code;
	}else{
		$code = "T_" . $code;
	}
	
	$code = str_replace(" ", "_", $code);
	$code = mb_strtoupper($code);
	
	CIBlockElement::SetPropertyValuesEx($arFields["ID"], false, array("WBARTICLE" => $code));
}
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>