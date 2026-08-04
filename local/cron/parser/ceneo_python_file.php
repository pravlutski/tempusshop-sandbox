#!/usr/bin/php
<?php

$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);
		
CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
global $DB;

list($usec, $sec) = explode(" ", microtime());
$time_start = ((float)$usec + (float)$sec);
$txt = "";

$filename = "/userscripts/python/ceneo.pl_result_" . date("Y-m-d") . ".csv";
//$filename = "/userscripts/python/ceneo.pl_result_2021-02-15.csv";
//подключаем класс для работы с csv
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/classes/csv.class.php');
$csv = new CSV($filename);
if($csv->error != "no_found"){

	$arCsv = array();
	$get_csv = $csv->getCSV();
	foreach ($get_csv as $key => $value){
	
		$tmp = explode("/",$value[2]);
		$tmp = array_diff($tmp, array(''));
		$ceneo_id = array_pop($tmp);
		$ceneo_id = intval($ceneo_id);
		
		$bitrix_id = intval($value[0]);
		$price = intval($value[3]);
		
		if($bitrix_id > 0 && $ceneo_id > 0){
			$arCsv[] = array(
				"BITRIX_ID" => $bitrix_id,
				"CENEO_ID" => $ceneo_id,
				"PRICE" => $price,
			);
		}

	}
}

$DB->Update("ci_ceneo_link", array("PARSE" => "'N'"), "", $err_mess.__LINE__);

$arIDs = array();
foreach($arCsv as $arItem){
	if($arItem["PRICE"] > 0){
		$arIDs[] = $arItem["CENEO_ID"];
	}
}
$in = array(
	"PARSE" => "'Y'",
); 
//$asd = "WHERE ceneo_id IN ('" . implode("','", $arIDs) ."')";
$DB->Update("ci_ceneo_link", $in, "WHERE ceneo_id IN ('" . implode("','", $arIDs) ."')", $err_mess.__LINE__);
			
//prent($asd,0,1);
/*
foreach($arCsv as $arItem){
	$strSql = "SELECT PARSE FROM ci_ceneo_link WHERE bitrix_id = '{$arItem["BITRIX_ID"]}' AND ceneo_id = '{$arItem["CENEO_ID"]}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if(!$row = $results->Fetch()){
		$in = array(
			"bitrix_id" => "'".$arItem["BITRIX_ID"]."'",
			"ceneo_id" => "'".$arItem["CENEO_ID"]."'",
			"PARSE" => ($arItem["PRICE"] > 0 ? "'Y'" : "'N'"),
			"PRICE" => $arItem["PRICE"],
		);
		//prent($in);die;
		$ID = $DB->Insert("ci_ceneo_link", $in, $err_mess.__LINE__);
		
	}elseif($row["PARSE"] == "N"){
		if($arItem["PRICE"] > 0){
			$in = array(
				"PARSE" => "'Y'",
			//	"PRICE" => $arItem["PRICE"],
			); 
			$DB->Update("ci_ceneo_link", $in, "WHERE bitrix_id = '{$arItem["BITRIX_ID"]}' AND ceneo_id = '{$arItem["CENEO_ID"]}'", $err_mess.__LINE__);
		}
	}
}*/

/*
//тут пишем в старый парсер цены отсюда
$rsEl = CIBlockElement::GetList(array("ID" => "ASC"), array("IBLOCK_ID" => 16,"ACTIVE" => "Y"), false, false, array("ID", "PROPERTY_CML2_ARTICLE"));
while($arFields = $rsEl->GetNext()){
	$arArticle[$arFields["ID"]] = $arFields["PROPERTY_CML2_ARTICLE_VALUE"];

}

$strSql = "SELECT * FROM ci_ceneo_link WHERE PARSE = 'Y' AND PRICE > 0";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while($row = $results->Fetch()){
	$arItems[] = array(
		"ARTICLE" => $arArticle[$row["bitrix_id"]],
		"BITRIX_ID" => $row["bitrix_id"],
		"CENEO_ID" => $row["ceneo_id"],
		"PRICE" => $row["PRICE"],
	);
}

$DB->Query("TRUNCATE TABLE ci_ceneo_price", false, $err_mess.__LINE__);

foreach($arItems as $arItem){
	$in = array(
		"name" => "'".addslashes($arItem["ARTICLE"])."'",
		"bitrix_id" => "'".addslashes($arItem["BITRIX_ID"])."'",
		"ceneo_id" => "'".addslashes($arItem["CENEO_ID"])."'",
		"minPrice" => "'".$arItem["PRICE"]."'",
		"minPrice2" => "'0'",
		"minPrice3" => "'0'",
		"type_price" => "'".addslashes("CENEO_CABINET")."'",
	);

	$DB->Insert("ci_ceneo_price", $in, $err_mess.__LINE__);
}
*/


//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>