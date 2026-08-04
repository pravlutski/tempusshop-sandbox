<?php

$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

const NO_KEEP_STATISTIC = true;
const NOT_CHECK_PERMISSIONS = true;
const BASE_URL = "https://tempusshop.ru";

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

set_time_limit(0);
ini_set('memory_limit', '1512M');

use Bitrix\Main\Loader;
use Bitrix\Main\Entity;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');
Loader::includeModule('panel.manager');

$logFile = __DIR__ . '/exchange_log.txt';

//wdhs
$CurDB = new DBPanel();

$result = $CurDB->Query("SELECT * FROM sites_brand_exclude");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  	$BrandExclude[] = $row;
}

print_r($BrandExclude);
