<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
global $USER;
if($USER->isAdmin() && !CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

//ищем процессы и убиваем если они есть
exec("pgrep -f /userscripts/del_cache.php",$output,$code); 
if(is_array($output) && count($output) > 0){
	foreach($output as $pid)
		exec("kill -9 {$pid}");
}
	
system("/usr/bin/php81 -f /userscripts/del_cache.php >/dev/null 2>&1 &");
// 
$res = array(
	'text' => ("asd")
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
