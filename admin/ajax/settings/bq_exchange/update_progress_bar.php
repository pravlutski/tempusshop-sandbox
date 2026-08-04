<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
global $USER;
//if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !$USER->isAdmin()) return;

$strSql = "SELECT * FROM bq_exchange";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["ITEMS"][] = $row;
}

$arReq = [];
foreach($arResult["ITEMS"] as $arItem){
	$fileProgress = $_SERVER["DOCUMENT_ROOT"] . "/local/cron/parser/logs/progress_{$arItem["ID"]}.lock";
	if(file_exists($fileProgress)){
		$status  = file_get_contents($fileProgress);
		//if($status == "100") continue;
		
		if(is_numeric($status)){
			$arReq[$arItem["ID"]] = ["text" => $status, "status" => "ok"];
		}else{
			$arReq[$arItem["ID"]] = ["text" => $status, "status" => "error"];
		}
	}else{
		$arReq[$arItem["ID"]] = ["text" => "Статус неопределен", "status" => "error"];
	}
}

echo json_encode($arReq, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
