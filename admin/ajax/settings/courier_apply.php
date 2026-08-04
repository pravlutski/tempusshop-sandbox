<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;
$in = array(
	"id" => intval($_POST["id"]),
	"name" => addslashes($_POST["name"]),
	"full_name" => addslashes($_POST["full_name"]),
	"sort" => intval($_POST["sort"]),
);
$id = intval($_POST["id"]);
$courier = new CPanelCourier;
$r = $courier->apply($in);
$res = array(
	'status' => ($r ? "ok" : "error"),
	'text' => ($r ? "Настройки сохранены" : "Не удалось сохранить")
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
