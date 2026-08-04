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

$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE" => "Y",
	"PROPERTY_AVAILABILITY_RU" => 512
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","NAME", "PROPERTY_WBARTICLE"));
while($ar = $rs->GetNext()){
	$bxItems[$ar["PROPERTY_WBARTICLE_VALUE"]] = $ar["ID"];
}

$arSettings = CMaxyssWb::settings_wb("WR");
$Authorization = $arSettings['AUTHORIZATION'];

$result = CMaxyssWb::getStock($Authorization, array(), 0);
/*
ob_start();
echo "<pre>" . print_r($result, true) . "</pre>";
$output = ob_get_clean();
file_put_contents("/userscripts/logs/wb_test.txt", serialize($output) . "\r\n", FILE_APPEND | LOCK_EX);
die;
*/
$arWB = array();
foreach($result["stocks"] as $key => $arItem){
	$article = $arItem["article"];
	$article[0] = "T";
	
	if($bxItems[$article]){
		$arWB[$article][$arItem["nmId"]] = array(
			"article" => $article,
			"cabinet" => ($arItem["warehouseName"] == "new" ? "WR" : "DEFAULT"),
			"nmId" => $arItem["nmId"],
		);
	}
	$status1 = true;
}

sleep(5);

$arSettings = CMaxyssWb::settings_wb("DEFAULT");
$Authorization = $arSettings["AUTHORIZATION"];

$result = CMaxyssWb::getStock($Authorization, array(), 0);

foreach($result["stocks"] as $key => $arItem){
	$article = $arItem["article"];
	
	if($bxItems[$article]){
		$arWB[$article][$arItem["nmId"]] = array(
			"article" => $article,
			"cabinet" => ($arItem["warehouseName"] == "new" ? "WR" : "DEFAULT"),
			"nmId" => $arItem["nmId"],
		);
	}

	$status2 = true;
}

if($status1 === true && $status2 === true){
	foreach($arWB as $article => $arItem){
		if(!$bxItems[$article]) continue;
		$arProp = array();
		foreach($arItem as $k => $v){
			$arProp[] = array("VALUE" => $v["nmId"], "DESCRIPTION" => $v["cabinet"]);
		}
		if($arProp){
			CIBlockElement::SetPropertyValuesEx($bxItems[$article], false, array("PROP_MAXYSS_NMID_CREATED_WB" => $arProp));
		}
	}
	//
}
echo count($arWB) . " элементов";

//prent($result,0,1);
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>