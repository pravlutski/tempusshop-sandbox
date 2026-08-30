<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("admin_modules_descGen_cron_descGenerator_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
require($_SERVER["DOCUMENT_ROOT"]."/admin/modules/descGen/classes/DescriptionGenerator.php");

$ogjGen = new DescriptionGenerator();
$ogjGen->cron();

 ?>
