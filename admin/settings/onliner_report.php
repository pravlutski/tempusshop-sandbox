<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule('panel.manager')) return;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/include/classes/api_onliner.php");
$obj = new Onliner_API;

$report_id = CProSet::getOption("UPDATE_ONLINER");
$arReport = json_decode($obj->report_pricelist($report_id), true);

$filepath = $_SERVER["DOCUMENT_ROOT"] . "/upload/onliner_report.csv";
$fp = fopen($filepath, 'w');
foreach($arReport as $report){
	$arItem[0] = $report["values"]["model"];
	$str_csv = implode(";", $arItem) . "\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);
}
file_force_download($filepath);
//prent($arReport);

//header('Content-Type: application/json;charset=UTF-8');
//die();
