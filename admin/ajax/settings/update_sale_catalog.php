<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

/*
//ищем процессы и убиваем если они есть
exec("pgrep -f /userscripts/update_sale_catalog.php",$output,$code);
if(count($output) > 0){
	foreach($output as $pid)
		exec("kill -9 {$pid}");
}

system("/usr/bin/php81 -f /userscripts/update_sale_catalog.php >>/userscripts/logs/update_sale_catalog.txt >/dev/null 2>&1 &");
*/
system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/UpdateSaleItems.php >/dev/null 2>&1 &");
//system("/usr/bin/php81 -f /userscripts/update_sale_catalog.php");

$res = array(
	'status' => ($result["status"] == "Y" ? "ok" : "error"),
	'text' => ($result["status"] == "Y" ? "Выгрузка прошла успешно" : "Не удалось выгрузить")
);
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/settings/bad.txt", print_r($result, true).PHP_EOL, FILE_APPEND);
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
