#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}
set_time_limit(3600);

$needStart = CProSet::getOption("UPDATE_PRICE_ANALISYS");

$logger = new TsLogger("/checkUpdateAnalisys/");
if ($needStart == 'NEED_START') {
	//ищем процессы и убиваем если они есть
	exec("pgrep -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_price_analys.php", $output, $code);
	if (count($output) > 0) {
		$logger->log("LOG", "Есть запущенные", $output);
		foreach($output as $pid) {
			$logger->log("LOG", "Прибиваем процесс");
			exec("kill -9 {$pid}");
		}
	}
	
	$logger->log("LOG", "Запускаем обмен");
	
	CProSet::setOption("UPDATE_PRICE_ANALISYS", "RUN");
	system("/usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_price_analys.php PRICE_ID=ALL >/dev/null 2>&1 &");
} else {
	$logger->log("LOG", "Пропускаем");
}

?>
