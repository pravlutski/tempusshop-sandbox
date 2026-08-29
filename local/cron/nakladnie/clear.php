<?
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}

if (file_exists('/var/www/bitrix/data/www/tempusshop.ru/upload/nakladnie_cache/')) {
    foreach (glob('/var/www/bitrix/data/www/tempusshop.ru/upload/nakladnie_cache/*') as $file) {
        unlink($file);
    }
}
