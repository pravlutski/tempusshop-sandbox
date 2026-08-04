<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

$triggers = new TsTriggers();
$triggers->SetError(["Запущен TopItemsWB. Пользователь: " . \Bitrix\Main\Engine\CurrentUser::get()->getLogin()]);
$triggers->SendTriggerErrors();

system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/TopItemsWB.php >/dev/null 2>&1 &");
//
$res = array(
	'text' => ("asd")
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
