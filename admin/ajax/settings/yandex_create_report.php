<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager')|| $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || 
	!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("catalog")) return;
$company_id = intval($_POST["yandex-company"]);
if($company_id > 0){
	$api = new MParserAPI;
	$arCompany = $api->setReportCompany($company_id);
	if(isset($arCompany["response"]["id"]) && $arCompany["response"]["id"] > 0){
		$res["status"] = "ok";
		$res["text"] = "Создан отчет - " . $arCompany["response"]["id"];
	}else{
		$res["status"] = "error";
		$res["text"] = serialize($arCompany);
	}

}else{
	$res["status"] = "error";
	$res["text"] = "Некорректный ID компании";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();

?>