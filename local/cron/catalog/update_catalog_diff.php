#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
// 
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);
//if (function_exists('ini_set')) ini_set('memory_limit','1512M');
//ищем процессы и убиваем если они есть
exec("pgrep -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_catalog_diff.php",$output,$code); 
if(count($output) > 0){
	foreach($output as $pid){
		$_output = array();
		exec("ps -p {$pid} -o etimes",$_output,$_code); 
		if(intval(trim($_output[1])) > 300){
			$arPID[] = $pid;
		}
		
	}
	foreach($arPID as $p){
		exec("kill -9 {$p}");
	}
	//prent($arPID);
}

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");

list($usec, $sec) = explode(" ", microtime());
$time_start = ((float)$usec + (float)$sec);

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