<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

//$obj = new CExchange("s1");
//$result = $obj->updateFromGoogleDocs(true);
//получаем московские из системы МойСклад
$obj = new CExchange("s1");
$result = $obj->updateFromMoySklad();
// var_dump($result);
// var_dump($result);
if($result["status"] == "Y"){
//	CProSet::setOption("UPDATE_CATALOG", "Y");
//	system("/usr/bin/php81 -f /userscripts/update_catalog_all.php >/dev/null 2>&1 &");
	$txt .= "Москва - " . $result["info"] . ". Обновлено - " . $result["cnt"];
	CLog::add2log(array("event" => "E", "text" => $txt));
}
$res = array(
	'status' => ($result["status"] == "Y" ? "ok" : "error"),
	'text' => ($result["status"] == "Y" ? "Выгрузка прошла успешно" : "Не удалось выгрузить"),
	'result' => $res
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
