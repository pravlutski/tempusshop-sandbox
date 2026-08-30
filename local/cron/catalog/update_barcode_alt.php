#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
// 
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("cron_catalog_update_barcode_alt_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(3600);
//if (function_exists('ini_set')) ini_set('memory_limit','1512M');

if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("currency") || !CModule::IncludeModule("catalog") || !CModule::IncludeModule("panel.manager")) return;
global $DB;

$objUtils = new CPanelUtils;
$arSelect = Array("ID");
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_CATALOG,
);

$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
while ($el = $result->GetNext()){
	$objUtils->updatePropBarcode($el["ID"]);
}

//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
$workers->updateStatus("N");
?>
