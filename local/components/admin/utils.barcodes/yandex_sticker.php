#!/usr/bin/php
<?
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(600);

if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") ||  !CModule::IncludeModule('panel.manager')) return;
?>
<?
require_once($_SERVER['DOCUMENT_ROOT'] . "/local/classes/YandexAPI.php");

foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $_REQUEST[$k] = $v;
}

if (!$_REQUEST["filename"]) return;
$filePath = "/var/www/bitrix_logs/yandex/{$_REQUEST["filename"]}";

if (!file_exists($filePath)) return;

$logger = new TsLogger("/yandex/sticker/");
$result = [
	"error" => [],
	//"no_sticker" => 0,
	"no_sticker" => [],
	"not_defined" => [],
];

$arNumbers = json_decode(file_get_contents($filePath), true);

foreach ($arNumbers as $setupId => $arItem) {
	
	$logger->log("LOG", "Проходим по setupId - " . $setupId);
	$ya = new YandexAPI($setupId); 
	
	foreach ($arItem as $ar) {
		
		$fileSticker = $_SERVER['DOCUMENT_ROOT'] . "/upload/stickers/yandex/{$ar['EXTERNAL_ORDER_ID']}.pdf";
		
		if (file_exists($fileSticker)) continue;

		$logger->log("LOG", "Запрашиваем", $ar);
	
		$sticker = $ya->getStickerFile($ar['EXTERNAL_ORDER_ID'], 'A9_HORIZONTALLY');
		
		if ($sticker && $sticker['reportId']) {
			// получаем результат
			sleep(1);
			$report = $ya->getReportResult($sticker['reportId']);
			//prent($report); 
			if ($report['file']) {
				// копируем файл к себе
				$fileContent = file_get_contents($report['file']);
				file_put_contents($fileSticker, $fileContent);
				$logger->log("LOG", "Получили стикер {$ar['EXTERNAL_ORDER_ID']}", [$report]); 
			} else {
				$logger->log("LOG", "Ошибка получения отчета", [$ar, $sticker['reportId'], $report]);
				$result["error"][] = "YA. {$ar['ORDER_ID']} - Ошибка получения отчета";
			}
		} else {
			$logger->log("LOG", "Ошибка запроса стикеров", [$ar, $sticker]);
			$result["error"][] = "YA. {$ar['ORDER_ID']} - Ошибка запроса стикеров";
		}
	}
	
}
echo json_encode($result);