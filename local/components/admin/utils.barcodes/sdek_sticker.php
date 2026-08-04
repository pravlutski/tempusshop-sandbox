#!/usr/bin/php
<?
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

set_time_limit(600);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 600);

if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") ||  !CModule::IncludeModule('panel.manager')) return;
?>
<?
require_once($_SERVER['DOCUMENT_ROOT'] . "/local/classes/SdekAPI.php");

foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $_REQUEST[$k] = $v;
}

if (!$_REQUEST["filename"]) return;
$filePath = "/var/www/bitrix_logs/sdek/{$_REQUEST["filename"]}";

if (!file_exists($filePath)) return;

$sdek = new SdekAPI();
$logger = new TsLogger("/sdek/sticker/");

$arNumber = json_decode(file_get_contents($filePath), true);

//$arNumber2 = [$arNumber[0]];
$logger->log("LOG", "filename", $_REQUEST["filename"]); 
$result = [
	"error" => [],
];
foreach($arNumber as $order_number) {
	$fileSticker = $_SERVER['DOCUMENT_ROOT'] . "/upload/stickers/sdek/{$order_number}.pdf";
	if (file_exists($fileSticker)) continue;

	$logger->log("LOG", "Запрашиваем {$order_number}");

	$data = [
		'orders' => [
			[
				'cdek_number' => $order_number,
			],
		],
		"copy_count" => 1,
		"format" => "A6",
		"lang" => "RUS"
	];

	$createSticker = $sdek->send(action: '/print/barcodes', data: $data, method: 'POST');

	$uuid = $createSticker["entity"]["uuid"] ?? false;
	
	if($uuid){
		$logger->log("LOG", "Задача для {$order_number} - {$uuid}");
		
		$send = true;
		$startTime = time(); // Запоминаем время начала выполнения

		do {
			usleep(1000); // 1 sec
			// смотрим статус
			$statusSticker = $sdek->send(action: "/print/barcodes/{$uuid}", method: 'GET');

			if(is_array($statusSticker['entity']) && isset($statusSticker['entity']['statuses'])){
				$is_ready = false;
				foreach ($statusSticker['entity']['statuses'] as $k => $v) {
					if ($v['code'] == 'READY') {
						$is_ready = true;
					}
				}
				
				if ($is_ready) {
					$headersFile = ['Accept: application/octet-stream'];
					$fileContent = $sdek->send(action: "/print/barcodes/{$uuid}.pdf", method: 'GET', headers: $headersFile);
					
					if ($fileSticker) {
						file_put_contents($fileSticker, $fileContent);
						$logger->log("LOG", "Получили стикер {$order_number}");
						$send = false;
					} else {
						usleep(1000);
					}
				} else {
					usleep(1000);
				}
				
				/*if ($res["status"] == "completed") {
					$send = false;
					// копируем файл к себе
					$fileContent = file_get_contents($res["file_url"]);
					file_put_contents($fileSticker, $fileContent);
					$logger->log("LOG", "Получили стикер {$order_number}", $res);
				} elseif($res["status"] == "error") {
					$send = false;
					$logger->log("ERROR", "Ошибка {$order_number}", $statusSticker);
					$cntBad++;
				} else {
					$logger->log("ERROR", "Статус неопределен {$order_number}", $statusSticker);
					$cntBad++;
				}*/
			} else {
				$send = false;
				$logger->log("ERROR", "Ошибка2 {$order_number}", $statusSticker);
				$result["error"][] = "SDEK. {$order_number} - статус неопределен. ответ не получен";
			}

			// Проверяем, не прошло ли 5 секунд
			if (time() - $startTime >= 5) {
				$send = false;
				$logger->log("ERROR", "Ошибка таймаут {$order_number}", ['statusSticker' => $statusSticker, 'createSticker' => $createSticker]);
				$result["error"][] = "SDEK. {$order_number} - 5 сек не получили ответ";
			}
		} while($send == true);
	} else {
		$result["error"][] = "SDEK. {$order_number} - Ошибка отправки запроса на формирование";
		$logger->log("ERROR", "Ошибка uuid не получен {$order_number}", $createSticker);
	}
}
echo json_encode($result);