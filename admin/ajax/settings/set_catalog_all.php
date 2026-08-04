<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;
/*
$obj = new CExchange("s1");
$result = $obj->updateFromGoogleDocs(true);
if($result["status"] == "Y"){
	CProSet::setOption("UPDATE_CATALOG", "Y");
	$txt .= "Москва - " . $result["info"] . ". Обновлено - " . $result["cnt"];
	CLog::add2log(array("event" => "E", "text" => $txt));
}
*/

//ищем процессы и убиваем если они есть
exec("pgrep -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_catalog_all.php",$output,$code); 
if(is_array($output) && count($output) > 0){
	foreach($output as $pid)
		exec("kill -9 {$pid}");
}
CProSet::setOption("UPDATE_CATALOG", "Y");
system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_catalog_all.php >/dev/null 2>&1 &");

$res = array(
	'status' => ($result["status"] == "Y" ? "ok" : "error"),
	'text' => ($result["status"] == "Y" ? "Выгрузка прошла успешно" : "Не удалось выгрузить")
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
