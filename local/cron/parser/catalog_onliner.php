#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");
CModule::IncludeModule("catalog");
	
set_time_limit(3600);
error_reporting(3600);
global $DB;

CProSet::setOption("PARSER_s2", "start");

if(CModule::IncludeModule("main") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog") && CModule::IncludeModule("panel.manager")){
	$obj = new COnlinerParser();
	$result = $obj->parse();
}

CProSet::setOption("PARSER_s2", "end");
?>
<?//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");?>