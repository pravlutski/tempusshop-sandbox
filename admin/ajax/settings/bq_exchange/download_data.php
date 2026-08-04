<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
global $USER;
if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

$ID = intval($_POST["ID"]);

system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/BqParse.php ACTION=parse ID={$ID} >/dev/null 2>&1 &");

/*
if($ID == 1){
	system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/msProfitDayItems.php >/dev/null 2>&1 &");
}elseif($ID == 2){
	system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/msProfitDayChannel.php >/dev/null 2>&1 &");
}

$res = array(
	'status' => "ok"
);
*/
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
