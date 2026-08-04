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
$TsTriggers = new TsTriggers();

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");

list($usec, $sec) = explode(" ", microtime());
$time_start = ((float)$usec + (float)$sec);
$txt = "";

if(CProSet::getOption("UPDATE_CATALOG") == "Y"){
	$lastID = 0;
	if(floatval(CProSet::getOption("UPDATE_CATALOG_PER")) < 100){
		$lastID = CProSet::getOption("UPDATE_CATALOG_LAST_ID") - 51;
		if($lastID < 0) $lastID = 0;
	}
	CProSet::setOption("UPDATE_CATALOG_PER", "0");
	$obj = new CExchange();
	$update = true;

	$cnt_avail = $cnt_stock = $cnt_no = 0;

	do {
		$up = $obj->updateCatalog($lastID);
		$lastID = $up["LAST_ID"];
		$cnt_avail += $up["CNT_AVAIL"];
		$cnt_stock += $up["CNT_STOCK"];
		$cnt_no += $up["CNT_NO"];
		if(CProSet::getOption("UPDATE_CATALOG") == "Y"){
			$txt = "Прервано на - " . CProSet::getOption("UPDATE_CATALOG_PER") . " %. ";
			$update = false;
		}
		if($up["PERCENT"] >= 100)
			$update = false;

	} while ($update == true);

	$txt .= "На складе - " . $cnt_avail . ", у поставщика - " . $cnt_stock . ", Нет артикула - " . $cnt_no;

	if($cnt_avail == 0 || $cnt_stock == 0 ||$cnt_no == 0){
		$TsTriggers->SetError(["Обновление каталога. " . $txt]);
		$TsTriggers->SendTriggerErrors();
	}

	//CPanelPricelist::updateDateDelivery();
	CPanelPricelist::updateProps();
//	CLog::add2log(array("event" => "U", "text" => $txt, "detail" => $obj->_log));
}
system("/usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/UpdateCollection.php >/dev/null 2>&1 &");
system("/usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/updaterProps.php >/dev/null 2>&1 &");
// sleep(30);
// system("/usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/updater.php >/dev/null 2>&1 &");
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
