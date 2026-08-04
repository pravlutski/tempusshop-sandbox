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
//if (function_exists('ini_set')) ini_set('memory_limit','1512M');

if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("currency") || !CModule::IncludeModule("catalog") || !CModule::IncludeModule("panel.manager")) return;
global $DB;

foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $_REQUEST[$k] = $v;
}

if(in_array($_REQUEST["PRICE_ID"], array("RU", "BY", "PL", "YA", "OS", "WB","AV", "SB", "WBTL","OZTI"))){

	$parser = CProSet::getOption("PARSER_{$_REQUEST["PRICE_ID"]}");
	if($_REQUEST["PRICE_ID"] == "YA") $parser = "end";

	$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_{$_REQUEST["PRICE_ID"]}");
	if($auto == "Y" || $_REQUEST["force"] == "Y"){
		$obj = new CPriceUpdate($_REQUEST["PRICE_ID"]);
		$obj->setAllPrice();
	}

}elseif($_REQUEST["PRICE_ID"] == "ALL"){

	$parser = CProSet::getOption("PARSER_s1");

	$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_RU");
	if($auto == "Y" || $_REQUEST["force"] == "Y"){
		$obj = new CPriceUpdate("RU");
		$obj->setAllPrice();
	}

	$parser = CProSet::getOption("PARSER_s2");

	$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_BY");
	if(($auto == "Y" || $_REQUEST["force"] == "Y") && $parser == "end"){
		$obj = new CPriceUpdate("BY");
		$obj->setAllPrice();
	}

	$parser = CProSet::getOption("PARSER_s3");

	$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_PL");
	if(($auto == "Y" || $_REQUEST["force"] == "Y") && $parser == "end"){
		$obj = new CPriceUpdate("PL");
		$obj->setAllPrice();
	}

	$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_YA");
	if($auto == "Y" || $_REQUEST["force"] == "Y"){
		$obj = new CPriceUpdate("YA");
		$obj->setAllPrice();
	}

	$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_OS");
	if($auto == "Y" || $_REQUEST["force"] == "Y"){
		$obj = new CPriceUpdate("OS");
		$obj->setAllPrice();
	}
	$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_WB");
	if($auto == "Y" || $_REQUEST["force"] == "Y"){
		$obj = new CPriceUpdate("WB");
		$obj->setAllPrice();
	}
	$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_WBTL");
	if($auto == "Y" || $_REQUEST["force"] == "Y"){
		$obj = new CPriceUpdate("WBTL");
		$obj->setAllPrice();
	}

	$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_OZTI");
	if($auto == "Y" || $_REQUEST["force"] == "Y"){
		$obj = new CPriceUpdate("OZTI");
		$obj->setAllPrice();
	}

	$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_AV");
	//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/test_price.txt", print_r($auto, true).PHP_EOL, FILE_APPEND);
	if($auto == "Y" ||$_REQUEST["force"] == "Y"){
		$obj = new CPriceUpdate("AV");
		$obj->setAllPrice();
	}

	$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_SB");
	//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/test_price.txt", print_r($auto, true).PHP_EOL, FILE_APPEND);
	if($auto == "Y" ||$_REQUEST["force"] == "Y"){
		$obj = new CPriceUpdate("SB");
		$obj->setAllPrice();
	}

    // $auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_OZKZ");
    // //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/test_price.txt", print_r($auto, true).PHP_EOL, FILE_APPEND);
    // if($auto == "Y" ||$_REQUEST["force"] == "Y"){
    //     $obj = new CPriceUpdate("OZKZ");
    //     $obj->setAllPrice();
    // }
		//
    // $auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_KZ");
    // //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/test_price.txt", print_r($auto, true).PHP_EOL, FILE_APPEND);
    // if($auto == "Y" ||$_REQUEST["force"] == "Y"){
    //     $obj = new CPriceUpdate("KZ");
    //     $obj->setAllPrice();
    // }

}
CPanelPricelist::updateDateDelivery();
echo date("Y-m-d H:i:s") . " - " . serialize($_REQUEST) . "\r\n";
//$path_tmp_file = "/userscripts/logs/tmp.txt";
//file_put_contents($path_tmp_file, serialize($_REQUEST));

//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
