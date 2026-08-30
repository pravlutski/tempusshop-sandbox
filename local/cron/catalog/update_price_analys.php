#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("price_analys_php_PRICE_ID_ALL");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(3600);
//if (function_exists('ini_set')) ini_set('memory_limit','1512M');

if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("currency") || !CModule::IncludeModule("catalog") || !CModule::IncludeModule("panel.manager")) return;
global $DB;

foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $_REQUEST[$k] = $v;
}

$logger = new \TsLogger("/update_price_analys/");

$service = PanelManager::getPriceManager();
$arPrices = $service->getTypePrices(); 

$arPriceID = array_column($arPrices, 'id');

if(in_array($_REQUEST["PRICE_ID"], $arPriceID)){
	$priceId = $_REQUEST["PRICE_ID"];
	$logger->log("LOG", "Запуск одного {$priceId}");
	try {
		$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_{$priceId}");
		if($auto == "Y" || $_REQUEST["force"] == "Y"){
			$servicePrice = $service->updatePriceService($priceId);
			$result = $servicePrice->updatePrices();			
			prent($result, 0,1);
			if (!$result['success']) {
				$logger->log("ERROR", "Ошибка при обновлении цен {$priceId}", $result);
			}
		}

	} catch (\Exception $e) {
		$logger->log("ERROR", "Грохнулся {$priceId}", $e->getMessage());
	}

}elseif($_REQUEST["PRICE_ID"] == "ALL") {
	$logger->log("LOG", "Запуск всех {$priceId}");
	foreach ($arPriceID as $priceId) {
		$logger->log("LOG", "Запуск {$priceId}");
        try {
			$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_{$priceId}");

			$servicePrice = $service->updatePriceService($priceId);
			if (($auto == "Y" || $_REQUEST["force"] == "Y")) {
				$result = $servicePrice->updatePrices();
				//prent($result);
				if (!$result['success']) {
					$logger->log("ERROR", "Ошибка при обновлении цен {$priceId}", $result);
				}
			}

        } catch (\Exception $e) {
			$logger->log("ERROR", "Грохнулся {$priceId}", $e->getMessage());
        }

	}
}
//CPanelPricelist::updateDateDelivery();
CPanelPricelist::updateProps();
?>
