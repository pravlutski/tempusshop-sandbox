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

$triggers = new TsTriggers();
$logger = new TsLogger("/updateStock/");
$workers = new WorkersChecker("updateStock");

// var_dump('start');
if (!$workers->checkStatus()) {
	$logger->log("LOG", "Обработчик занят");
	exit();
}

$logger->log("LOG", "Запуск обработчика");

$workers->updateStatus("Y");

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");

$supplier = new CPanelSupplier();
$arSupplier = $supplier->getList();

$time_start = debug_microtime_float();

$arStocks = [
	[
		"cabinet" => "s1",
		//"name" => "Склад Москва (любое время до 15:00)",
	],
	[
		"cabinet" => "s2",
		//"name" => "Склад Минск (любое время до 14:30 + 1 Д)",
	],
	[
		"cabinet" => "s2_nemiga",
	],
	[
		"cabinet" => "s3",
		//"name" => "Склад Польша",
	],
	[
		"cabinet" => "msk",
		//"name" => "Алексей/Стас № 2 (Москва, ПН-ВС 9-14(15) | 11-16)",
	],
	/*[
		"cabinet" => "s1_fbo",
		//"name" => "Склад Москва FBO",
	],*/
	[
		"cabinet" => "s1_store",
		//"name" => "Склад Москва FBO",
	],
	[
		"cabinet" => "s1_2",
		//"name" => "Склад Москва 2 (любое время до 15:00)",
	],
	[
		"cabinet" => "msk_gk",
		//"name" => "Склад GK",
	],
	/*[
		"cabinet" => "msk2",
		//"name" => "Алексей/Стас № 1 (Москва, ПН-ВС 14 | 18)",
	],*/
	[
		"cabinet" => "s1_import",
		//"name" => "Склад Москва Импорт",
	],
	[
		"cabinet" => "s1_import2",
		//"name" => "Склад Москва Импорт",
	],
];

$arRes = [];
$send = false;
foreach($arStocks as &$stock){
	$step_start = debug_microtime_float();

	$exch = new CExchange($stock["cabinet"]);
	$stock["name"] = $arSupplier[$exch->supplier_id]["name"];

	$logger->log("LOG", "Получаем {$stock["name"]}");

	$res = $exch->updateFromMoySklad();
	$step_end = debug_microtime_float();

	$logger->log("LOG", "result {$stock["name"]}", [
		"Время выполнения" => debug_microtime_float() - $step_start,
		"res" => $res,
	]);

	$arRes[] = [
		"status" => $res["status"],
		"cnt" => $res["cnt"],
		"cabinet" => $stock["cabinet"],
		"name" => $stock["name"],
	];
	if($res["status"] == "N"){
		$send = true;
		$logger->log("ERROR", "Ошибка обновления склада. ", [$stock, $res]);
	}
}
unset($stock);
//CPanelPricelist::updateDateDelivery();

$workers->updateStatus("N");

// добавляем в лог админки
$message = "";
foreach($arRes as $arItem){
	$message .= "<p>{$arItem["name"]}, статус - {$arItem["status"]}, {$arItem["cnt"]}</p>";
}
$message .= "<p>Время обновления складов " . (debug_microtime_float() - $time_start) . "</p>";
CLog::add2log(array("event" => "E", "detail" => $message));

// отправляем письмо и в телегу, если были ошибки
if($send === true){
	$message = "<p>Ошибка при обновлении склада</p>";

	foreach($arRes as $arItem){
		if($arItem["status"] == "N"){
			$message .= "<p>{$arItem["cabinet"]} - {$arItem["name"]} не получен</p>";
		}
	}

	$arFields = array(
		"EMAIL_TO" => "",
		"SUBJECT" => "Ошибка при обновлении склада",
		"MESSAGE" => $message,
	);

	CEvent::SendImmediate("IM_NEW_MESSAGE", array("s1"), $arFields, "N", 405);

	$triggers->SetError(["Обновление складов. " . $message]);
	$triggers->SendTriggerErrors();
}

//
//CPanelPricelist::updateProps();

//$orderReserved = PanelManager::getOrderReservedManager();
//$orderReserved->updateReserved();

CExchange::updateReserved();

//PanelManager::getPriceManager()->truncatePriceService();
Panel\Manager\Service\CatalogPriceService::updatePriceProps();

/*
//получаем московские из системы МойСклад
$logger->log("LOG", "Получаем s1");
$step_start = debug_microtime_float();
$obj1 = new CExchange("s1");
$res1 = $obj1->updateFromMoySklad();
$step_end = debug_microtime_float();

$logger->log("LOG", "s1", $res1);
//check

file_put_contents("/home/bitrix/logs/wdhs/updateStock.txt", print_r('res1 READY:' . ($step_end - $step_start) ,true) . "\r\n", FILE_APPEND);
//получаем минские из системы МойСклад
$step_start = debug_microtime_float();
$logger->log("LOG", "Получаем s2");
$obj2 = new CExchange("s2");
$res2 = $obj2->updateFromMoySklad();
print_r($res2);
$logger->log("LOG", "s2", $res2);
$step_end = debug_microtime_float();
//check
file_put_contents("/home/bitrix/logs/wdhs/updateStock.txt", print_r('res2 READY:' . ($step_end - $step_start) ,true) . "\r\n", FILE_APPEND);
//получаем польские из системы МойСклад
$step_start = debug_microtime_float();
$logger->log("LOG", "Получаем s3");
$obj3 = new CExchange("s3");
$res3 = $obj3->updateFromMoySklad();
$logger->log("LOG", "s3", $res3);
$step_end = debug_microtime_float();
//check
file_put_contents("/home/bitrix/logs/wdhs/updateStock.txt", print_r('res3 READY:' . ($step_end - $step_start) ,true) . "\r\n", FILE_APPEND);
$step_start = debug_microtime_float();
$logger->log("LOG", "Получаем msk");
$obj = new CExchange("msk");
$resOpt = $obj->updateFromMoySklad();
$logger->log("LOG", "msk", $resOpt);
$step_end = debug_microtime_float();
//check
$time_start = debug_microtime_float();
//получаем московские из системы МойСклад
$logger->log("LOG", "Получаем s1_fbo");
$step_start = debug_microtime_float();
$obj4 = new CExchange("s1_fbo");
$res4 = $obj4->updateFromMoySklad();
$step_end = debug_microtime_float();

$logger->log("LOG", "s1_2", $res1);
//check

//получаем московские из системы МойСклад
$logger->log("LOG", "Получаем s1_2");
$step_start = debug_microtime_float();
$obj5 = new CExchange("s1_2");
$res5 = $obj5->updateFromMoySklad();
$step_end = debug_microtime_float();

$logger->log("LOG", "s1_2", $res1);
//check

file_put_contents("/home/bitrix/logs/wdhs/updateStock.txt", print_r('resopt READY:' . ($step_end - $step_start) ,true) . "\r\n", FILE_APPEND);
$step_start = debug_microtime_float();
$obj = new CExchange("msk2");
$resOpt2 = $obj->updateFromMoySklad();
$step_end = debug_microtime_float();
//check
// file_put_contents("/home/bitrix/logs/wdhs/updateStock.txt", print_r('resopt2 READY:' . ($step_end - $step_start) ,true) . "\r\n", FILE_APPEND);
//получаем Заказы поставщиков
//$obj1 = new CExchange("s1_order");
//$res1 = $obj1->updateFromMoySkladOrder();
$time_end = debug_microtime_float();
$txt = "Москва - " . $res1["info"];
$txt .= "Москва опт - " . $resOpt["info"];
$txt .= "Москва опт2 Алексей/Стас № 1 (Москва, ПН-ВС 14 | 18) - " . $resOpt2["info"];
$txt .= ". Минск - " . $res2["info"];
$txt .= ". Польша - " . $res3["info"];
$txt .= ". МОСКВА ФБО - " . $res4["info"];
$txt .= ". МОСКВА 2 - " . $res5["info"];
if($res1["status"] == "Y" || $res2["status"] == "Y" || $res3["status"] == "Y" || $res4["status"] == "Y" || $res5["status"] == "Y" || $resOpt["status"] == "Y" || $resOpt2["status"] == "Y"){
//	CProSet::setOption("UPDATE_CATALOG", "Y");
	$txt .= ". Обновлено - Москва - " . $res1["cnt"] . ", Москва опт - " . $resOpt["cnt"] . ", Москва опт2 - " . $resOpt2["cnt"] . ", Минск - " . $res2["cnt"] . ", Польша - " . $res3["cnt"]. ", МОСКВА 2 - " . $res5["cnt"].", МОСКВА ФБО - " . $res4["cnt"];
	$txt .= ". " . date("Y-m-d H:i:s") . ". Времея обновления складов " . ($time_end - $time_start);

	CLog::add2log(array("event" => "E", "detail" => $txt));
	//check
	file_put_contents("/home/bitrix/logs/wdhs/updateStock.txt", print_r($txt,true) . "\r\n", FILE_APPEND);
}

if($res1["status"] == "N" || $res2["status"] == "N" || $res3["status"] == "N" || $res4["status"] == "N" || $resOpt["status"] == "N" || $resOpt2["status"] == "N"){
	$message = "<p>Ошибка при обновлении склада</p>";
	if($res1["status"] == "N")
		$message .= "<p>s1 не получен</p>";
	if($res2["status"] == "N")
		$message .= "<p>s2 не получен</p>";
	if($res3["status"] == "N")
		$message .= "<p>s3 не получен</p>";
	if($res4["status"] == "N")
		$message .= "<p>s1_fbo не получен</p>";
	if($res5["status"] == "N")
		$message .= "<p>s1_2 не получен</p>";
	if($resOpt["status"] == "N")
		$message .= "<p>s1 опт не получен</p>";
	// if($resOpt2["status"] == "N")
	// 	$message .= "<p>s1 опт2 не получен</p>";
	$arFields = array(
		"EMAIL_TO" => "",
		"SUBJECT" => "Ошибка при обновлении склада",
		"MESSAGE" => $message,
	);
	//check
	file_put_contents("/home/bitrix/logs/wdhs/updateStock.txt", print_r($message,true) . "\r\n", FILE_APPEND);

	CEvent::SendImmediate("IM_NEW_MESSAGE", array("s1"), $arFields, "N", 405);

	$logger->log("LOG", "Ошибка обновления складов. " . $message);

	$triggers->SetError(["Обновление складов. " . $message]);
	$triggers->SendTriggerErrors();
}

CExchange::updateReserved();

$workers->updateStatus("N");
$logger->log("LOG", $txt);
//check
file_put_contents("/home/bitrix/logs/wdhs/updateStock.txt", print_r('[!!!End!!!]',true) . "\r\n", FILE_APPEND);
*/
?>
