<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule("crm_courier") || !CModule::IncludeModule("panel.manager")) return;

$APPLICATION->IncludeComponent("adm.courier:order.list.accept", "", $arParams, false);

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');