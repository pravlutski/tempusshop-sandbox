<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

//ищем процессы и убиваем если они есть
exec("pgrep -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/set_item_props.php",$output,$code); 
if(is_array($output) && count($output) > 0){
	foreach($output as $pid)
		exec("kill -9 {$pid}");
}
	
system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/set_item_props.php >>/home/bitrix/logs/wb/set_item_props.txt >/dev/null 2>&1 &");
// 
$res = array(
	'text' => ("asd")
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
