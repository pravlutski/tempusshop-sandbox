<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || 
$_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$id = (int)$_POST["id"];
$content = new CPanelContent;
$content->removeTask($id);

header('Content-Type: application/json;charset=UTF-8');
die();
