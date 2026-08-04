#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
// Обновляем товары которые изменились при загрузках прайсов.
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");

$obj = new CExchange();
$update = true;

$rs = $obj->updateCatalogDiff();
if($rs['status'] == 'update'){
	CProSet::setOption("UPDATE_CATALOG_DIFF", "Y");
	//CPanelPricelist::updateDateDelivery($rs['items'] ?? []);
	CPanelPricelist::updateProps($rs['items'] ?? []);
}
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>