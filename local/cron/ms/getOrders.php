#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
// 
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("local_cron_ms_getOrders_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(3600);
//if (function_exists('ini_set')) ini_set('memory_limit','1512M');

if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("currency") || !CModule::IncludeModule("catalog") || !CModule::IncludeModule("panel.manager")) return;

function setOrderMS($site_id = "s1"){
	global $DB;

	$strSql = "SELECT MS_ID FROM ci_ms_order WHERE SITE_ID = '{$site_id}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arOrderMS[$row["MS_ID"]] = $row["MS_ID"];
	}

	$obj = new MoyskladAPI($site_id);
	$obj->MSPosition = array();
	
	$arDB = array();

	$obj->getListOrder(0, "", true);
	
	foreach($obj->MSPosition as $key => $arItem){
		$arDB[$arItem["id"]] = array(
			"ORDER_NUMBER" => $arItem["name"],
			"MS_ID" => $arItem["id"],
			"META" => $arItem["meta"],
			"DATA" => $arItem,
		);
	}

	foreach($arDB as $key => $arItem){
		if(!$arOrderMS[$arItem["MS_ID"]]){
			$in = array(
				"ORDER_NUMBER" => "'".addslashes($arItem["ORDER_NUMBER"])."'",
				"SITE_ID" => "'".addslashes($site_id)."'",
				"MS_ID" => "'".addslashes($arItem["MS_ID"])."'",
				"META" => "'" . json_encode($arItem["META"]) . "'",
				"DATA" => "'" . json_encode($arItem["DATA"]) . "'",
			);
			
			//пишем всё во временную таблицу сразу
			$DB->Insert("ci_ms_order", $in, $err_mess.__LINE__);
		}
	}
	
}

setOrderMS("s1");
setOrderMS("s2");
setOrderMS("s3");

//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
$workers->updateStatus("N");
?>
