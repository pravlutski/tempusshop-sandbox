#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("cron_other_update_all_props_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(3600);



CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");

$arNeed = $arAlready = $arTemps = array();

$CurDB =  new DBPanel();
$strSql = "SELECT bitrix_id, model FROM ci_top_models WHERE site_id = 's1'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arNeed[$row["bitrix_id"]] = $row["bitrix_id"];
	$arTemps[$row["bitrix_id"]] = $row["model"];
}

$result = $CurDB->query("SELECT VALUE FROM sites_settings WHERE SETTING = 'STICKER_NEW_DAYS'");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $daysNew = $row['VALUE'];
}


$arSelect = array("ID", "PROPERTY_HIT",'PROPERTY_CML2_ARTICLE');
$arFilter = Array(
	"IBLOCK_ID" => 16,
	"PROPERTY_HIT_VALUE" => "Да"
);

$res = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
while($arFld = $res->GetNext()){
	$arAlready[$arFld["ID"]] = $arFld["ID"];
	$arTemps[$arFld["ID"]] = $arFld["PROPERTY_CML2_ARTICLE_VALUE"];
}

foreach($arNeed as $ID){
	if(!$arAlready[$ID]){
		if (!isset($arTemps[$ID])) {
			$arTemps[$ID] = 'UNDEFIND';
		}
		$addArray[] = [
			'PROPS' => 'HIT',
			'ARTICLE' => $arTemps[$ID],
			'BITRIX_ID' => $ID,
			'PREV' => 'Нет',
			'NEXT' => 'Да',
			'DATE' => date('d.m.Y H:i:s'),
		];
		$CurDB->insert('site_props_log', $addArray);
		CIBlockElement::SetPropertyValueCode($ID, "HIT", 29);
	}
}

foreach($arAlready as $ID){
	if(!$arNeed[$ID]){
		if (!isset($arTemps[$ID])) {
			$arTemps[$ID] = 'UNDEFIND';
		}
		$addArray[] = [
			'PROPS' => 'HIT',
			'ARTICLE' => $arTemps[$ID],
			'BITRIX_ID' => $ID,
			'PREV' => 'Да',
			'NEXT' => 'Нет',
			'DATE' => date('d.m.Y H:i:s'),
		];
		$CurDB->insert('site_props_log', $addArray);
		CIBlockElement::SetPropertyValueCode($ID, "HIT", 1933);

	}
}


##NEWS
$arNeed = $arAlready = $arTemps = array();


$arSelect = array("ID", "PROPERTY_NEWEST",'PROPERTY_CML2_ARTICLE');
$arFilter = Array(
	"IBLOCK_ID" => 16,
	"PROPERTY_NEWEST_VALUE" => "Да"
);

$res = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
while($arFld = $res->GetNext()){
	$arAlready[$arFld["ID"]] = $arFld["ID"];
	$arTemps[$arFld["ID"]] = $arFld["PROPERTY_CML2_ARTICLE_VALUE"];
}


$arSelect = array("ID", "PROPERTY_NEWEST", 'PROPERTY_CML2_ARTICLE');
$arFilter = Array(
    "IBLOCK_ID" => 16,
		"ACTIVE" => 'Y',
		// "!PROPERTY_CML2_ARTICLE" => false,
    ">=DATE_CREATE" => ConvertTimeStamp(strtotime('-'.$daysNew.' days'), "FULL")
);

$res = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
while($arFld = $res->GetNext()){
	$arNeed[$arFld["ID"]] = $arFld["ID"];
	$arTemps[$arFld["ID"]] = $arFld["PROPERTY_CML2_ARTICLE_VALUE"];
}

$hardItems = [
	[
		'ID' => 210120,
		'ARTICLE' => 'T137.427.11.011.01',
	],
	[
		'ID' => 209875,
		'ARTICLE' => 'T137.210.11.421.00',
	],
	[
		'ID' => 210103,
		'ARTICLE' => 'T160.110.36.126.00',
	],
	[
		'ID' => 209457,
		'ARTICLE' => 'T156.410.11.091.00',
	],
];
foreach($hardItems as $item){
	$arNeed[$item["ID"]] = $item["ID"];
	$arTemps[$item["ID"]] = $item["ARTICLE"];
}
/*
Tissot PRX T137.427.11.011.01
Tissot PRX T137.210.11.421.00
Tissot SRV T160.110.36.126.00
Tissot Ballade T156.410.11.091.00
*/
foreach($arNeed as $ID){
	if(!$arAlready[$ID]){
		if (!isset($arTemps[$ID])) {
			$arTemps[$ID] = 'UNDEFIND';
		}
		$addArray[] = [
			'PROPS' => 'NEW',
			'ARTICLE' => $arTemps[$ID],
			'BITRIX_ID' => $ID,
			'PREV' => 'Нет',
			'NEXT' => 'Да',
			'DATE' => date('d.m.Y H:i:s'),
		];
		$CurDB->insert('site_props_log', $addArray);
		CIBlockElement::SetPropertyValueCode($ID, "NEWEST", 2088);
	}
}

foreach($arAlready as $ID){
	if(!$arNeed[$ID]){
		if (!isset($arTemps[$ID])) {
			$arTemps[$ID] = 'UNDEFIND';
		}
		$addArray[] = [
			'PROPS' => 'NEW',
			'ARTICLE' => $arTemps[$ID],
			'BITRIX_ID' => $ID,
			'PREV' => 'Да',
			'NEXT' => 'Нет',
			'DATE' => date('d.m.Y H:i:s'),
		];
		$CurDB->insert('site_props_log', $addArray);
		CIBlockElement::SetPropertyValueCode($ID, "NEWEST", 2087);

	}
}


$workers->updateStatus("N");
?>
