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

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule("crm_courier");
CModule::IncludeModule("intaro.retailcrm");

global $DB;

global $logger;;
$logger = new TsLogger("/ms/createDocumentsMS/");

$workersChecker = new WorkersChecker("createDocumentsMS");

if (!$workersChecker->checkStatus()) {
	$logger->log("LOG", "Обработчик занят");
	exit();
}

$workersChecker->updateStatus("Y");

$arResult["ORDER_STATUS_SEND_MS"] = json_decode(CProSet::getOption("ORDER_STATUS_SEND_MS"), true);

function insertDocMS($arItem = array()){
	global $DB;
	$in = array(
		"ORDER_NUMBER" => "'".addslashes($arItem["ORDER_NUMBER"])."'",
		"STATUS" => "'".addslashes($arItem["STATUS"])."'",
		"TYPE" => "'".addslashes($arItem["TYPE"])."'",
		"USER_ID" => "'cron'",
	);

	$strSql = "SELECT * FROM ci_ms_create_documents WHERE ORDER_NUMBER = '{$arItem["ORDER_NUMBER"]}' AND TYPE = '{$arItem["TYPE"]}'";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);

	if (!$row = $results->Fetch()){
		$DB->Insert("ci_ms_create_documents", $in, $err_mess.__LINE__);
	}

}


function createDocumentMS($arOrder = array(), $type = "DEMAND"){
	global $logger;
	if(!$arOrder) return false;
	if(!CModule::IncludeModule("panel.manager")) return;

	global $DB;
	$objMS = new MoyskladAPI("s1");
	$strSql = "SELECT * FROM ci_ms_order WHERE ORDER_NUMBER IN ('" . implode("','", array_keys($arOrder)) . "') AND SITE_ID = 's1'";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);

	while ($row = $results->Fetch()){
		$arResult["ORDER_MS"][$row["ORDER_NUMBER"]] = $row;
	}

	if(count_($arResult["ORDER_MS"]) != count_($arOrder)){
		foreach($arOrder as $find => $ar){
			if(!$arResult["ORDER_MS"][$find]){
				$arResult["ERROR"][] = "<p style='color:red;'>MS. {$find} - не найден</p>";
				$arResult["LOG"][$find]["TEXT"] .= " {$find} - MS. Заказ не найден для отправки";
				$arResult["LOG"][$find]["STATUS"] = "ERROR";
			}
		}
	}
	$arLog = array(
		"DATE" => date("Y-m-d H:i:s"),
		"TEXT" => $arResult,
		"type" => $type,
		"USER_ID" => "cron",
	);
	//file_put_contents("/home/bitrix/logs/utils_set_status_order_" . date("Y_m_d") . ".txt", serialize($arLog) . "\r\n", FILE_APPEND | LOCK_EX);

	if(count_($arResult["ORDER_MS"]) > 0){
		$arSend = array();
		foreach($arResult["ORDER_MS"] as $key => $arItem){

			$resOrder = $objMS->customRequest("https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$arItem["MS_ID"]}");
			if(!$resOrder || $resOrder["errors"]){
				$logger->log("ERROR", "Ошибка запроса " . "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$arItem["MS_ID"]}", $resOrder);
				$arResult["ERROR"][] = "<p style='color:red;'>MS. Данные по заказу {$arItem["ORDER_NUMBER"]} не получены</p>";

				$arResult["LOG"][$arItem["ORDER_NUMBER"]]["TEXT"] .= " MS. Данные по заказу {$arItem["ORDER_NUMBER"]} не получены";
				$arResult["LOG"][$arItem["ORDER_NUMBER"]]["STATUS"] = "ERROR";
				continue;
			}

			if($type == "DEMAND" && $resOrder["demands"] && count($resOrder["demands"]) > 0){
				$arResult["ERROR"][] = "<p style='color:red;'>MS. По заказу {$arItem["ORDER_NUMBER"]} уже есть отгрузка</p>";

				$arResult["LOG"][$arItem["ORDER_NUMBER"]]["TEXT"] .= " {$arItem["ORDER_NUMBER"]} - MS. Уже есть отгрузка";
				$arResult["LOG"][$arItem["ORDER_NUMBER"]]["STATUS"] = "ERROR";

				insertDocMS(array("ORDER_NUMBER" => $arItem["ORDER_NUMBER"], "STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"], "TYPE" => $type));

				continue;
			}

			if($type == "DEMAND"){
				$data = array(
					"href" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$arItem["MS_ID"]}",
					"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata",
					"type" => "customerorder",
					"mediaType" => "application/json",
				);

				$arTemplate = $objMS->getDemandTemplate($data);

				if($arTemplate["customerOrder"]){

					$resMS = $objMS->setDemand(array($arTemplate));

					foreach($resMS as $k => $arMS){
						if(!$arMS["id"]){
							if($arMS["errors"]){
								$logger->log("ERROR", "Ошибка запроса DEMAND", ["data" => $data, "arTemplate" => $arTemplate, "arMS" => $arMS,]);
								foreach($arMS["errors"] as $k => $v){
									$arResult["ERROR"][] = "<p style='color:red;'>MS. Заказ - {$key}. {$v["error"]}</p>";

									$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. {$v["error"]}";
									$arResult["LOG"][$key]["STATUS"] = "ERROR";

								}
							}elseif($arMS["error"]){
								$logger->log("ERROR", "Ошибка запроса DEMAND 2", ["data" => $data, "arTemplate" => $arTemplate, "arMS" => $arMS,]);
								$arResult["ERROR"][] = "<p style='color:red;'>MS. Заказ - {$key}. {$arMS["error"]}</p>";

								$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. {$arMS["error"]}";
								$arResult["LOG"][$key]["STATUS"] = "ERROR";
							}else{
								$logger->log("ERROR", "Ошибка запроса DEMAND 3", ["data" => $data, "arTemplate" => $arTemplate, "arMS" => $arMS,]);
								$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Ошибка не определена";
							}
						}else{
							$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Создана отгрузка";

							insertDocMS(array("ORDER_NUMBER" => $arItem["ORDER_NUMBER"], "STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"], "TYPE" => "DEMAND"));

						}
					}
				}else{
					$logger->log("ERROR", "Ошибка запроса DEMAND. Шаблон создания не удалось получить", ["data" => $data, "arTemplate" => $arTemplate,]);
					$arResult["ERROR"][] = "<p style='color:red;'>MS. Заказ - {$key}. Шаблон создания не удалось получить</p>";
					$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Шаблон создания не удалось получить";
					$arResult["LOG"][$key]["STATUS"] = "ERROR";
				}
			}elseif($type == "SALES_RETURN"){

				if($resOrder["demands"]){
					$demand = $resOrder["demands"][0];
					//смотрим есть ли возврат
					$resDemand = $objMS->customRequest($demand["meta"]["href"]);
					if($resDemand["returns"] && count($resDemand["returns"]) > 0){
						$arResult["ERROR"][] = "<p style='color:red;'>MS. По заказу {$arItem["ORDER_NUMBER"]} уже есть возврат</p>";

						$arResult["LOG"][$arItem["ORDER_NUMBER"]]["TEXT"] .= " {$arItem["ORDER_NUMBER"]} - MS. Уже есть возврат";
						$arResult["LOG"][$arItem["ORDER_NUMBER"]]["STATUS"] = "ERROR";

						insertDocMS(array("ORDER_NUMBER" => $arItem["ORDER_NUMBER"], "STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"], "TYPE" => "SALES_RETURN"));

						continue;
					}else{

						$arTemplate = $objMS->getSalesReturnTemplate($demand["meta"]);

						if($arTemplate["demand"]){

							$resMS = $objMS->setSalesReturn(array($arTemplate));

							foreach($resMS as $k => $arMS){
								if(!$arMS["id"]){
									if($arMS["errors"]){
										$logger->log("ERROR", "Ошибка запроса SALES_RETURN", ["demand" => $demand, "arTemplate" => $arTemplate, "arMS" => $arMS,]);
										foreach($arMS["errors"] as $k => $v){
											$arResult["ERROR"][] = "<p style='color:red;'>MS. Возврат. Заказ - {$key}. {$v["error"]}</p>";

											$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. {$v["error"]}";
											$arResult["LOG"][$key]["STATUS"] = "ERROR";

										}
									}elseif($arMS["error"]){
										$logger->log("ERROR", "Ошибка запроса SALES_RETURN 2", ["demand" => $demand, "arTemplate" => $arTemplate, "arMS" => $arMS,]);
										$arResult["ERROR"][] = "<p style='color:red;'>MS. Возврат. Заказ - {$key}. {$arMS["error"]}</p>";

										$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. {$arMS["error"]}";
										$arResult["LOG"][$key]["STATUS"] = "ERROR";
									}else{
										$logger->log("ERROR", "Ошибка запроса SALES_RETURN 3", ["demand" => $demand, "arTemplate" => $arTemplate, "arMS" => $arMS,]);
										$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. Ошибка не определена";
									}
								}else{
									$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Создан возврат";
									insertDocMS(array("ORDER_NUMBER" => $arItem["ORDER_NUMBER"], "STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"], "TYPE" => "SALES_RETURN"));
								}
							}

						}else{
							$logger->log("ERROR", "Ошибка запроса SALES_RETURN 4", ["demand" => $demand, "arTemplate" => $arTemplate,]);
							$arResult["ERROR"][] = "<p style='color:red;'>MS. Возврат. Заказ - {$key}. Шаблон создания не удалось получить</p>";
							$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. Шаблон создания не удалось получить";
							$arResult["LOG"][$key]["STATUS"] = "ERROR";
						}

					}
				}else{
					$logger->log("ERROR", "Ошибка запроса SALES_RETURN 5", ["demand" => $demand, "arTemplate" => $arTemplate,]);
					$arResult["ERROR"][] = "<p style='color:red;'>MS. По заказу {$arItem["ORDER_NUMBER"]} нет отгрузки</p>";

					$arResult["LOG"][$arItem["ORDER_NUMBER"]]["TEXT"] .= " MS. {$arItem["ORDER_NUMBER"]} - нет отгрузки для создания возврата";
					$arResult["LOG"][$arItem["ORDER_NUMBER"]]["STATUS"] = "ERROR";
				}
			}

		}

	}

	foreach($arResult["LOG"] as $key => $arItem){
		if($arItem["STATUS"] == "OK"){
			$txt = "<p>{$arItem["TEXT"]}</p>";
		}else{
			$txt = "<p style='color:red;'>{$arItem["TEXT"]}</p>";
		}
		$arLog = array(
			"DATE" => date("Y-m-d H:i:s"),
			"TEXT" => $txt,
			"USER_ID" => "cron",
		);
		//file_put_contents("/home/bitrix/logs/utils_set_status_order_" . date("Y_m_d") . ".txt", serialize($arLog) . "\r\n", FILE_APPEND | LOCK_EX);
	}
}
//берем заказы для создания отгрузок
$obj = new OrderService;
$obj->getPropOrderFlg = true;
$arOrder = array();
if($arResult["ORDER_STATUS_SEND_MS"]["DEMAND"]){
	$arFilter = array(
		"STATUS_ID" => $arResult["ORDER_STATUS_SEND_MS"]["DEMAND"],
		">=DATE_INSERT" => date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), time() - 30 * 86400),
		"LID" => "s1",
	);

	$order = $obj->getOrder(array(), $arFilter);

	foreach($order as $key => $arItem){
		$arOrder[$arItem["ORDER_ID"]] = $arItem;
	}
	unset($order);
}


//очищаем от того что уже по чему отгрузки создавались
$strSql = "SELECT * FROM ci_ms_create_documents WHERE TYPE = 'DEMAND'";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()){
	if($arOrder[$row["ORDER_NUMBER"]]){
		unset($arOrder[$row["ORDER_NUMBER"]]);
	}
}
//если что то осталось, то работаем дальше
if(count_($arOrder) > 0){
	$logger->log("LOG", "Кол-во документов на создание DEMAND - " . count_($arOrder));
	createDocumentMS($arOrder, "DEMAND");
}

/******************* возвраты **************************/

$arOrder = array();
if($arResult["ORDER_STATUS_SEND_MS"]["SALES_RETURN"]){
	$arFilter = array(
		"STATUS_ID" => $arResult["ORDER_STATUS_SEND_MS"]["SALES_RETURN"],
		">=DATE_INSERT" => date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), time() - 30 * 86400),
		"LID" => "s1",
	);

	$order = $obj->getOrder(array(), $arFilter);

	foreach($order as $key => $arItem){
		$arOrder[$arItem["ORDER_ID"]] = $arItem;
	}
	unset($order);
}

//prent($arOrder);die;
//очищаем от того что уже по чему отгрузки создавались
$strSql = "SELECT * FROM ci_ms_create_documents WHERE TYPE = 'SALES_RETURN'";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()){
	if($arOrder[$row["ORDER_NUMBER"]]){
		unset($arOrder[$row["ORDER_NUMBER"]]);
	}
}
//если что то осталось, то работаем дальше
if(count_($arOrder) > 0){
	$logger->log("LOG", "Кол-во документов на создание SALES_RETURN - " . count_($arOrder));
	//prent($arOrder);
	createDocumentMS($arOrder, "SALES_RETURN");
}

$workersChecker->updateStatus("N");
$logger->log("LOG", "Конец обработки");
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
