<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
global $USER;
if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

$APPLICATION->IncludeComponent("adm:bq.exchange.directory", "ajax", array(), false);
