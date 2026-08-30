#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
$workers = new WorkersChecker("local_cron_other_clear_log_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");

$logger = new TsLogger("/DeleteLogs/");
$logger->clearFolders();

//удаляем логи старше 90 дней
global $DB;
$DB->Query("DELETE FROM ci_log WHERE timestamp < (NOW() - INTERVAL 90 DAY)", false, $err_mess.__LINE__);

?>