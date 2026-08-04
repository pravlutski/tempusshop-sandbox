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

global $DB;

function getUrlCurl($urlrest){

		$process = curl_init($urlrest);
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Accept: application/json", 
				"Content-Type: application/json", 
			)
		);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		$result = curl_exec($process);

	return $result;
}

$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE" => "Y",
	"PROPERTY_AVAILABILITY_RU" => 512
);

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","NAME", "PROPERTY_WBARTICLE"));
while($ar = $rs->GetNext()){
	$bxItems[$ar["ID"]] = $ar["ID"];
}

$res = $DB->Query("SELECT * FROM b_iblock_element_prop_m16 WHERE IBLOCK_PROPERTY_ID = '2796'");
while ($ar = $res->Fetch()){
	if($bxItems[$ar["IBLOCK_ELEMENT_ID"]] && $ar["VALUE"]){
		$nmID[$ar["IBLOCK_ELEMENT_ID"]][] = array(
			"IBLOCK_ELEMENT_ID" => $ar["IBLOCK_ELEMENT_ID"],
			"VALUE" => $ar["VALUE"],
			"DESCRIPTION" => $ar["DESCRIPTION"],
		);
	}
}

foreach($nmID as $id => $arData){
	$arProp = array();
	foreach($arData as $key => $arItem){
		$rs = getUrlCurl("https://wbx-content-v2.wbstatic.net/ru/{$arItem["VALUE"]}.json");
		$rs = json_decode($rs, true);
		if($rs["imt_id"]){
			$arProp[] = array("VALUE" => $rs["imt_id"], "DESCRIPTION" => $arItem["DESCRIPTION"]);
		}
	}
	if($arProp){
		CIBlockElement::SetPropertyValuesEx($id, false, array("PROP_MAXYSS_CARDID_WB" => $arProp));
	}
}
echo count($nmID);
//prent($result,0,1);
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>