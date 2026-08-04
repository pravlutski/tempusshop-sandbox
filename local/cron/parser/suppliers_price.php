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
if(CModule::IncludeModule("panel.manager")){
	$objSupplier = new CPanelSupplier;
	$arSupplier = $objSupplier->getList();
	$arList = array();
	foreach($arSupplier as $key => $arItem){
		$ar = $arItem;
		$ar["settings_pricelist"] = json_decode( $arItem["settings_pricelist"], true );
		
		$path_parts = pathinfo($ar["settings_pricelist"]["filename"]);
		//echo $path_parts['filename'] . "<br>";
		//if($path_parts['filename'])
		//	echo "/var/www/bitrix/data/www/tempusshop.ru/scripts/" . $path_parts['filename'] . ".php";
		if(strlen($ar["settings_pricelist"]["filename"]) > 0 && file_exists("/var/www/bitrix/data/www/tempusshop.ru/scripts/" . $path_parts['filename'] . ".php")){
			//$path_parts = pathinfo("/var/www/bitrix/data/www/tempusshop.ru/scripts/" . $path_parts['filename'] . ".php");
			$arList[] = $path_parts['filename'];
		}
	}
	foreach($arList as $key => $file){
		//echo $file . "<br>";
		$output = file_get_contents("https://tempusshop.ru/scripts/{$file}.php");
	}
}
die;
$output = file_get_contents('https://tempusshop.ru/scripts/parser_watchtown_opt.php');
//$output = file_get_contents('http://tempusshop.ru/scripts/parser_watchtown.php');
$output = file_get_contents('https://tempusshop.ru/scripts/parser_1010.php');
$output = file_get_contents('https://tempusshop.ru/scripts/parser.php');
$output = file_get_contents('https://tempusshop.ru/scripts/parser_clock.php');
$output = file_get_contents('https://tempusshop.ru/scripts/parser_punktualni.php');
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>