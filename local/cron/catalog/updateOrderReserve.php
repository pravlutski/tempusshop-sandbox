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

$triggers = new TsTriggers();
$logger = new TsLogger("/updateOrderReserve/");
$workers = new WorkersChecker("updateOrderReserve");

// var_dump('start');
if (!$workers->checkStatus()) {
	//$logger->log("LOG", "Обработчик занят");
	//exit();
}

$logger->log("LOG", "Запуск обработчика");

//$workers->updateStatus("Y");

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");

//$workers->updateStatus("N");

CExchange::updateReserved();

Panel\Manager\Service\CatalogPriceService::updatePriceProps();
?>
