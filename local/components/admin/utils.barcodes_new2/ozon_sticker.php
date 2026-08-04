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
require_once($_SERVER['DOCUMENT_ROOT'] . "/local/classes/OzonAPI.php");

foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $_REQUEST[$k] = $v;
}

if (!$_REQUEST["filename"]) return;
$filePath = "/var/www/bitrix_logs/ozon/{$_REQUEST["filename"]}";

if (!file_exists($filePath)) return;

$ozon = new OzonAPI("IP");
$logger = new TsLogger("/ozon/sticker/");

$arNumber = json_decode(file_get_contents($filePath), true);

//$arNumber2 = [$arNumber[0]];
//$logger->log("LOG", "arNumber2", $arNumber2); 
$cntBad = 0;
foreach($arNumber as $order_number) {
	$fileSticker = $_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_number}.pdf";
	
	if (file_exists($fileSticker)) continue;

	$logger->log("LOG", "Запрашиваем {$order_number}");
	
	$task_id = $ozon->createTaskSticker([$order_number]);
	
	if($task_id){
		$logger->log("LOG", "Задача для {$order_number} - {$task_id}");
		
		$send = true;
		$startTime = time(); // Запоминаем время начала выполнения

		do {
			usleep(200); // 0.2 sec
			$res = $ozon->getStickerFile($task_id); // Получаем файл с этикетками

			if(is_array($res) && isset($res["status"])){
				if ($res["status"] == "completed") {
					$send = false;
					// копируем файл к себе
					$fileContent = file_get_contents($res["file_url"]);
					file_put_contents($fileSticker, $fileContent);
					$logger->log("LOG", "Получили стикер {$order_number}", $res);
				} elseif($res["status"] == "error") {
					$send = false;
					$logger->log("ERROR", "Ошибка {$order_number}", $res);
					$cntBad++;
				} else {
					$logger->log("ERROR", "Статус неопределен {$order_number}", $res);
					$cntBad++;
				}
			}else{
				$send = false;
				$logger->log("ERROR", "Ошибка2 {$order_number}", $res);
				$cntBad++;
			}

			// Проверяем, не прошло ли 5 секунд
			if (time() - $startTime >= 5) {
				$send = false;
				$logger->log("ERROR", "Ошибка таймаут {$order_number}", $res);
				$cntBad++;
			}
		} while($send == true);
		
		
		
	} else {
		$cntBad++;
		$logger->log("ERROR", "Ошибка task_id не получен {$order_number}", $res);
	}
}
echo $cntBad;