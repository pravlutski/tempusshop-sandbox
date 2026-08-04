#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);
if (function_exists('ini_set')) ini_set('memory_limit','1512M');

if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("currency") || !CModule::IncludeModule("catalog")) return;
if(!CModule::IncludeModule("panel.manager")) die("no module");
global $DB;

class WbTopItems(){
	
}

function checkCount($ID){

	if(!$ID) return false;
	global $DB;
		
	$strSql = "SELECT * FROM ci_reserved WHERE PRODUCT_ID = '{$ID}'";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		if(($row["AVAILABLE_RU"] - $row["RESERVED"]) > 2) return true;
		if(($row["AVAILABLE_BY"] - $row["RESERVED"]) > 2) return true;
		if(($row["AVAILABLE_PL"] - $row["RESERVED"]) > 2) return true;
	}else{
		return true;
	}
				
	return false;
}

$strSql = "SELECT ID, XML_ID FROM b_iblock_element WHERE IBLOCK_ID = '16'";
		
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arXML[$row["XML_ID"]] = $row["ID"];
}

$strSql = "SELECT el.ID as ID, el.XML_ID as XML_ID, pr.PROPERTY_123 as ARTICLE 
	FROM 
		b_iblock_element el 
	LEFT JOIN 
		b_iblock_element_prop_s16 pr 
	ON el.ID=pr.IBLOCK_ELEMENT_ID 
	WHERE 
		el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";
	
$arArticle = array();	
$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()){
	if(strlen($row["ARTICLE"]) > 0)
		$arArticle[$row["ARTICLE"]] = $row;
}

$obj = new MoyskladAPI("s1");
$obj->MSPosition = array();

$obj->getListProfit(0, false);

$arPosition = $obj->MSPosition;

//$file_log = "/userscripts/logs/ms_wb_top_" . date("Y-m-d") . ".txt";
//file_put_contents($file_log, serialize($arPosition) . "/r/n", FILE_APPEND | LOCK_EX);

if(count_($arPosition) > 0){
	foreach($arPosition as $key => &$arItem){
		
		if($arXML[$arItem["XML_ID"]]){
			$arItem["BITRIX_ID"] = $arXML[$arItem["XML_ID"]];
			$arItem["IS_MOYSKLAD"] = true;
		}elseif($arArticle[$arItem["ARTICLE"]]){
			$arItem["BITRIX_ID"] = $arArticle[$arItem["ARTICLE"]]["ID"];
			$arItem["IS_MOYSKLAD"] = true;
		}else{
			$arLog = array(
				"event" => "ER",
				"text" => "MS. Получение ТОП для WB. Не найдет ID в битриксе",
				"detail" => $arItem,
			);
			CLog::add2log($arLog);
			unset($arPosition[$key]);
		}
	}
	unset($arItem);
}elseif($obj->LAST_ERROR){
	CLog::add2log(array("event" => "E", "text" => $obj->LAST_ERROR));
}


//добавляем в массив новинки. не старше 6 месяцев
$from = date("d.m.Y H:i:s", strtotime("-6 month"));
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE"	=> "Y",
	">DATE_CREATE" => $from,
	"!PROPERTY_CML2_ARTICLE" => false,
	"PROPERTY_AVAILABILITY_RU" => array(512),
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "XML_ID", "IBLOCK_ID", "NAME", "DATE_CREATE","PROPERTY_CML2_ARTICLE"));
while($ar_fields = $rs->GetNext()){
	if(!$arPosition[$ar_fields["XML_ID"]]){
		$arPosition[$ar_fields["XML_ID"]] = array(
			"BITRIX_ID" => $ar_fields["ID"],
			"XML_ID" => $ar_fields["XML_ID"],
			"ARTICLE" => $ar_fields["PROPERTY_CML2_ARTICLE_VALUE"],
		);
	}
	
}

//добавляем в массив товары из свойства Наши предложения со значением Распродажа
$from = date("d.m.Y H:i:s", strtotime("-6 month"));
$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE"	=> "Y",
	"PROPERTY_HIT" => array(164),//Распродажа
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "XML_ID", "IBLOCK_ID", "NAME", "DATE_CREATE","PROPERTY_CML2_ARTICLE"));
while($ar_fields = $rs->GetNext()){
	if(!$arPosition[$ar_fields["XML_ID"]]){
		$arPosition[$ar_fields["XML_ID"]] = array(
			"BITRIX_ID" => $ar_fields["ID"],
			"XML_ID" => $ar_fields["XML_ID"],
			"ARTICLE" => $ar_fields["PROPERTY_CML2_ARTICLE_VALUE"],
		);
	}
	
}
		
$objPricelist = new CPanelPricelist;
/*
$arFilter = array(
	"supplier_id" => array(88, 41),
	"active" => "Y"
);
$price = $objPricelist->getPriceByFilter($arFilter);

foreach($price as $key => $arItem){
	if($arArticle[$arItem["model"]] && !$arPosition[$arArticle[$arItem["model"]]["XML_ID"]]){
		$arPosition[$arArticle[$arItem["model"]]["XML_ID"]] = array(
			"BITRIX_ID" => $arArticle[$arItem["model"]]["ID"],
			"XML_ID" => $arArticle[$arItem["model"]]["XML_ID"],
			"ARTICLE" => $arArticle[$arItem["model"]]["ARTICLE"],
		);
	}else{
		
	}
}
*/
// добавляем в массив всё со склад Москва, если больше 2 остаток
$arFilter = array(
	"supplier_id" => array(47, 103),
	"price_id" => "wb"
);
$price = $objPricelist->getPriceByFilter($arFilter);

foreach($price as $key => $arItem){
	if($arArticle[$arItem["model"]] && !$arPosition[$arArticle[$arItem["model"]]["XML_ID"]] && checkCount($arArticle[$arItem["model"]]) === true){
		$arPosition[$arArticle[$arItem["model"]]["XML_ID"]] = array(
			"BITRIX_ID" => $arArticle[$arItem["model"]]["ID"],
			"XML_ID" => $arArticle[$arItem["model"]]["XML_ID"],
			"ARTICLE" => $arArticle[$arItem["model"]]["ARTICLE"],
		);
	}
}

foreach($arPosition as $key => $arItem){
	if(!$arItem["BITRIX_ID"]) continue;
	
	if($arItem["IS_MOYSKLAD"] === true){
		$arFavorit[] = $arItem["BITRIX_ID"];
	}
		
}

if(count_($arPosition) > 0){
	$DB->Query("TRUNCATE TABLE ci_wb_top", false, $err_mess.__LINE__);

	foreach($arPosition as $key => $arItem){
		if(!$arItem["BITRIX_ID"]) continue;
		$in = array(
			"bitrix_id" => "'".addslashes($arItem["BITRIX_ID"])."'",
			"article" => "'".addslashes($arItem["ARTICLE"])."'",
		);

		$DB->Insert("ci_wb_top", $in, $err_mess.__LINE__);
			
	}
}

//if(date("H") == 1 || 1==1){
if(count_($arFavorit) > 0){

	//снимаем свойство у всех товаров и проставляем новые

	$rs = CIBlockElement::GetList(array(), array("PROPERTY_FAVORIT_ITEM" => 1125), false, false, array("ID", "NAME"));
	$arDeact = array();
	while($ar = $rs->GetNext()){
		$arDeact[] = $ar["ID"];
	}

	foreach($arDeact as $key => $product_id){
		CIBlockElement::SetPropertyValuesEx($product_id, false, array("FAVORIT_ITEM" => false));
	}

	$arFavorit = array_unique($arFavorit);

	foreach($arFavorit as $key => $product_id){
		CIBlockElement::SetPropertyValuesEx($product_id, false, array("FAVORIT_ITEM" => 1125));
	}
}


?>