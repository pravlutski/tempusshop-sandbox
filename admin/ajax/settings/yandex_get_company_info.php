<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$id = intval($_POST["id"]);
if(!CModule::IncludeModule('panel.manager')|| $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || 
	!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("catalog")) return;
$res["status"] = "error";

if($id > 0){
	$api = new MParserAPI;
	$res = $api->getPriceInfo($id);
	$arInfo = $res["response"];
	$txt = "";
	switch($arInfo["status"]){
		case "ERROR": $txt .= "Ошибка!!!"; $res["status"] = "error"; break;
		case "PARSED": $txt .= "Обработка началась."; $res["status"] = "error"; break;
		case "READY_TO_BE_PARSED": $txt .= "Готов к созданию отчета. ХЗ что за статус!!!!"; $res["status"] = "error"; break;
		case "SEARCH_IN_PROGRESS": $txt .= "Поиск работает. Нельзя создать отчет."; $res["status"] = "error"; break;
		case "PROCESSED": $txt .= "Обработан. Готов к созданию отчета."; $res["status"] = "ok"; break;
		case "NOT_ENOUGH_BALANCE_TO_PROCESS": $txt .= "Не достаточный баланс."; $res["status"] = "error"; break;
		case "PARSED_BUT_TRIAL_PRICE_SIZE_LIMIT_EXCEEDED": $txt .= "Не возможно спарсить. лимит в 200 триал версия."; $res["status"] = "error";break;
	}
	/*
	if($arInfo["isSuccessfullyProcessed"] == 1)
		$txt .= "Можно создать отчет. ";
	else
		$txt .= "Cоздать отчет по компании нельзя. ";
	*/
	$txt .= " Обновлено - " . date("d.m.Y H:i", strtotime($arInfo["createdAt"])) . ".";
	$txt .=  " Позиций - " . $arInfo["countNotEmptyRows"] . ", дублей - " . $arInfo["countFoundDuplicatedRows"];
}

$res["text"] = $txt;

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
?>