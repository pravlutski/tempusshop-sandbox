<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

//ищем процессы и убиваем если они есть
exec("pgrep -f /userscripts/parse_ceneo.php",$output,$code); 
if(is_array($output) && count($output) > 0){
	foreach($output as $pid)
		exec("kill -9 {$pid}");
}
CProSet::setOption("PARSE_CENEO", "Y");
system("/usr/bin/php81 -f /userscripts/parse_ceneo.php >/dev/null 2>&1 &");

$res = array(
	'status' => ($result["status"] == "Y" ? "ok" : "error"),
	'text' => ($result["status"] == "Y" ? "Выгрузка прошла успешно" : "Не удалось выгрузить")
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
