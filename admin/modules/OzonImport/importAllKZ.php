<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

require_once 'importProductsKZ.php';
require_once 'importPricesKZ.php';
require_once 'importStockKZ.php';

(new OzonImportProductsKZ())->run();
(new OzonImportPricesKZ())->run();
(new OzonImportStocksKZ())->run();
