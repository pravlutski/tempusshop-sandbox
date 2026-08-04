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
$filePath = "/var/www/bitrix_logs/ozon/tmp/{$_REQUEST["filename"]}";

if (!file_exists($filePath)) return;

$ozon = new OzonAPI("IP");
$logger = new TsLogger("/ozon/sticker/");

$arNumber = json_decode(file_get_contents($filePath), true);

//$arNumber2 = [$arNumber[0]];
$logger->log("LOG", "filename", $_REQUEST["filename"]); 
$cntBad = 0;
$log = [];
foreach($arNumber as $order_number) {
	$fileSticker = $_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/{$order_number}.pdf";
	if ($order_number == '88021715-0526-1') {
		$logger->log("LOG", "88021715-0526-1", $fileSticker);
	}
	if (file_exists($fileSticker)) continue;
	if ($order_number == '88021715-0526-1') {
		$logger->log("LOG", "нету");
	}
	$logger->log("LOG", "Запрашиваем {$order_number}");
	usleep(200);
	$createSticker = $ozon->createTaskSticker([$order_number]);
	
	$task_id = $createSticker["result"]["task_id"] ?? 0;
	
	if($task_id > 0){
		$logger->log("LOG", "Задача для {$order_number} - {$task_id}");
		
		$send = true;
		$startTime = time(); // Запоминаем время начала выполнения

		do {
			usleep(500); // 0.5 sec
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
					$log['errors'][] = '1 - ' . $order_number . ' ' . serialize($res);//serialize($res);
				} elseif($res["status"] == "pending" || $res["status"] == "in_progress") {
					$logger->log("ERROR", $res["status"] . " {$order_number}", $res);
					usleep(2000);
				} else {
					$logger->log("ERROR", "Статус неопределен {$order_number}", $res);
					$cntBad++;
					$log['errors'][] = '2 - ' . $order_number . ' ' . serialize($res);
				}
			}else{
				$send = false;
				$logger->log("ERROR", "Ошибка2 {$order_number}", $res);
				$cntBad++;
				$log['errors'][] = '3 - ' . $order_number . ' ' . serialize($res);
			}

			// Проверяем, не прошло ли 5 секунд
			if (time() - $startTime >= 5) {
				$send = false;
				$logger->log("ERROR", "Ошибка таймаут {$order_number}", $res);
				$cntBad++;
				$log['errors'][] = $order_number . ' таймаут';
			}
		} while($send == true);
		
		
		
	} else {
		$cntBad++;
		$logger->log("ERROR", "Ошибка task_id не получен {$order_number}", $createSticker);
		$log['errors'][] = $order_number . ' task_id не получен' . serialize($createSticker);
	}
}
$log['cntBad'] = $cntBad;
echo json_encode($log);