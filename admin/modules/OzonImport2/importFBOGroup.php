<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

require_once 'checkFboNew.php';
require_once 'importPricesFBO.php';
require_once 'importStockFBO.php';

(new checkFBONEW())->run();
(new OzonImportPricesFBO())->run();
(new OzonImportStocksFBO())->run();
