<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager')) return;

global $USER;

$logData = [
    "date" => date("Y-m-d H:i:s"),
    "user_id" => $_POST['user_id'] ?? $USER->GetID(),
    "filename" => $_POST['filename'] ?? '',
    "profile" => $_POST['profile'] ?? '',
    "processed" => $_POST['processed'] ?? 0,
    "errors" => $_POST['errors'] ?? 0
];

$logDir = "/home/bitrix/logs/set_prop_json/";
if(!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

file_put_contents($logDir . "apply_changes_log.txt", 
    json_encode($logData, JSON_UNESCAPED_UNICODE) . "\n", 
    FILE_APPEND);

echo json_encode(['success' => true]);