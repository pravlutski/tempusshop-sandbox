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
if(CModule::IncludeModule("panel.manager")){
	$objSupplier = new CPanelSupplier;
	$objPrice = new CPanelPricelist;
	
	$arSupplier = $objSupplier->getList();
	$arList = array();
	foreach($arSupplier as $key => $arItem){
		$ar = $arItem;
		$ar["settings"] = json_decode( $arItem["settings"], true );
		$ar["settings_pricelist"] = json_decode( $arItem["settings_pricelist"], true );

		$path_parts = pathinfo($ar["settings_pricelist"]["filename"]);
		//if(strlen($ar["settings_pricelist"]["filename"]) > 0 && file_exists("/var/www/bitrix/data/www/tempusshop.ru/upload/parser/" . $ar["settings_pricelist"]["filename"])){
		if(strlen($ar["settings_pricelist"]["filename"]) > 0 && file_exists("/var/www/bitrix/data/www/tempusshop.ru/scripts/" . $path_parts['filename'] . ".php")){
			$ar["filename"] = $path_parts['filename'] . ".xlsx";//$ar["settings_pricelist"]["filename"];
			$arList[] = $ar;
		}
	}

	if(count($arList) > 0){
		$message = "";
		foreach($arList as $key => $arItem){
			$tmp = $arItem["settings"]["brand"];
			$form = array();
			foreach($tmp as $k => $v)
				$form["brand"][] = $v["id"];
			$filename = "/var/www/bitrix/data/www/tempusshop.ru/upload/parser/" . $arItem["filename"];
			
			$arItem["settings_pricelist"]["col_price"] = "4";
			$arItem["settings_pricelist"]["col_count"] = "3";
			//$arItem["settings_pricelist"]["count_default"] = "";
			//$arItem["settings_pricelist"]["quntity_flag"] = "";
			//$arItem["settings_pricelist"]["quntity_value"] = "";
			$arItem["settings_pricelist"]["col_article"] = "2";
			$arItem["settings_pricelist"]["col_brand"] = "1";
			$arItem["settings_pricelist"]["start_row"] = "3";

			
			//prent($arItem,0,1);die;
			$res = $objPrice->upload($filename, $form, $arItem);
			
			$message .= "<p>{$arItem["filename"]}</p>";
			$message .= $res["diff"];
			//$html .= "";
//			prent($asd);
			//prent($form);
			//prent($arItem);

		}
		//отправляем письмо
		$toSend["TEXT"] = $message;
		CEvent::SendImmediate("SALE_STATUS_CHANGED_NA", "s1", $toSend, "Y", 181);
	}
	//prent($arList);
}

set_time_limit(0);

//$output = file_get_contents('http://tempusshop.ru/scripts/parser_watchtown_opt.php?cron=Y');

//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>