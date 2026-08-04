<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
// Отвечаем только на Ajax
//if ($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {return;}

if(!CModule::IncludeModule('panel.manager'))return;
global $USER;
global $DB;

$logger = new TsLogger("/utils/set_status_order/");
if (!class_exists('WildberriesAPI')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/WildberriesAPI.php';
}

function insertDocMS($arItem = array()){
	global $DB;
	global $USER;
	$in = array(
		"ORDER_NUMBER" => "'".addslashes($arItem["ORDER_NUMBER"])."'",
		"STATUS" => "'".addslashes($arItem["STATUS"])."'",
		"TYPE" => "'".addslashes($arItem["TYPE"])."'",
		"USER_ID" => "'".$USER->getID()."'",
	);
	$strSql = "SELECT * FROM ci_ms_create_documents WHERE ORDER_NUMBER = '{$arItem["ORDER_NUMBER"]}' AND TYPE = '{$arItem["TYPE"]}'";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);

	if (!$row = $results->Fetch()){
		$DB->Insert("ci_ms_create_documents", $in, $err_mess.__LINE__);
	}
}

function findOrderByTrackCode( array $numbers )
{
	// Временно
	if ( empty($numbers) ) die('Empty track numbers array. Died to prevent wrong selection');

	$dbRes = \Bitrix\Sale\ShipmentCollection::getList([
	  'select' => ['ORDER_ID'],
	  'filter' => [
			'=TRACKING_NUMBER' => $numbers
		]
	]);

	$result = [];

	while ( $item = $dbRes->fetch() ) {
	  $result[] = $item['ORDER_ID'];
	}

	if ( count($numbers ?? []) != count($result ?? []) ) die('Mismatch between request and response');

	return $result;
}

$arLog = array(
	"DATE" => date("Y-m-d H:i:s"),
	"POST" => $_POST,
	"USER_ID" => $USER->getID(),
);
$logger->log("LOG", "arLog", [$arLog]);

// Можно передавать в скрипт разный action и в соответствии с ним выполнять разные действия.

$obj = new OrderService;
$arResult["STATUS"] = $obj->getStatusOrderList();

$arList = explode("\r\n", $_POST["list_order"]);
$arList = array_diff($arList, array(''));
// var_dump($arList);
// die;
$action = $_POST['action'];

if (empty($action)) {return;}

if(!$arResult["STATUS"][$_POST["status"]]){
	$arResult["ERROR"][] = "<p style='color:red;'>Выберите статус</p>";
}

if(is_array($arList) && count($arList) <= 0){
	$arResult["ERROR"][] = "<p style='color:red;'>Введите номера заказов</p>";
}

if($action == "stop"){
	$arResult["ERROR"][] = "<p style='color:red;'>Отмена</p>";
}

if($arResult["ERROR"]){
	$output = Array('offset' => 0, 'sucsess' => 1, 'error' => $arResult["ERROR"], 'info' => '');

	echo json_encode($output, JSON_UNESCAPED_UNICODE);

	header('Content-Type: application/json;charset=UTF-8');
	die();
}

$count = count($arList);
$step = 5;

// Получаем от клиента номер итерации
$offset = $_POST['offset'];

if($offset == 0){
	$_SESSION["STATUS_ORDER_CNT"] = 0;
	$_SESSION["STATUS_MS_ACTION_CNT"] = 0;
	$_SESSION["SUPPLIER_CREATE_WB"] = false;
}

$arListFilter = array_slice($arList, $offset, $step);

ob_start();

$source = $_POST["source"];
if ($source == 'wb_by' || $source == 'ozon_by') {
	$cabinet = 's2';
} else {
	$cabinet = 's1';
}

$isAllowed = true;
if ( $source == 'site-tn' && $_POST['status'] == 'F' ){
	$arResult["ERROR"][] = "<p style='color:red;'>Запрещено переводить статус заказа в \"Выполнен\" по трек-коду</p>";
	$isAllowed = false;
}

if(is_array($arListFilter) && count($arListFilter) > 0 && $isAllowed){

	if($source == "wb"){
		$prop = "MAXYSS_OP_STICKER";
		$arFilter = array("PROPERTY_VAL_BY_CODE_MAXYSS_OP_STICKER" => $arListFilter);
		$wb = new WildberriesAPI("WR");
	} elseif($source == "wb_by"){
		$prop = "MAXYSS_OP_STICKER";
		$arFilter = array("PROPERTY_VAL_BY_CODE_MAXYSS_OP_STICKER" => $arListFilter);
		$wb = new WildberriesAPI("WB_BY");
	}  elseif($source == "wb_tl"){
		$prop = "MAXYSS_OP_STICKER";
		$arFilter = array("PROPERTY_VAL_BY_CODE_MAXYSS_OP_STICKER" => $arListFilter);
		$wb = new WildberriesAPI("TL");
	} elseif($source == "wb_by"){
		$prop = "MAXYSS_OP_STICKER";
		$arFilter = array("PROPERTY_VAL_BY_CODE_MAXYSS_OP_STICKER" => $arListFilter);
		$wb = new WildberriesAPI("WB_BY");
	} elseif( ($source == "ozon" || $source == "ozon_by") && $_POST['typeNumber'] == 'number' ){
		$prop = "OZON_NUMBER";
		$arFilter = array("PROPERTY_VAL_BY_CODE_OZON_NUMBER" => $arListFilter);
	} elseif( ($source == "ozon" || $source == "ozon_by") && $_POST['typeNumber'] == 'barcode' ){
		$prop = "OZON_LOWER_BARCODE";
		$arFilter = array("PROPERTY_VAL_BY_CODE_OZON_LOWER_BARCODE" => $arListFilter);
	} elseif($source == "sber"){
		$prop = "SBER_ID";
		$arFilter = array("PROPERTY_VAL_BY_CODE_SBER_ID" => $arListFilter);
	}	elseif($source == "yandex"){
			$prop = "ORDER_NUMBER_YA";
			$arFilter = array("PROPERTY_VAL_BY_CODE_ORDER_NUMBER_YA" => $arListFilter);
	}	elseif($source == "id"){
			$prop = "ID";
			$arFilter = array("ID" => $arListFilter);
	} elseif( $source == "site_tn" ){
			$prop = "ID";
			$arFilter = array(
				"ID" => findOrderByTrackCode($arListFilter) ?? 0
			);
	} else{
		$prop = "ORDER_ID";
		$arFilter = array("ACCOUNT_NUMBER" => $arListFilter);
	}

	// создаем новую поставку.
	$supplierWB = false;
	if(in_array($source, ['wb', 'wb_tl', 'wb_by']) && $_POST["status"] == "F"){
		if($source == "wb" && $_POST["supplier-wb"]) {
			$suppWB = trim($_POST["supplier-wb"]);
		} elseif ($source == "wb_tl" && $_POST["supplier-wb_tl"]) {
			$suppWB = trim($_POST["supplier-wb_tl"]);
		} elseif ($source == "wb_by" && $_POST["supplier-wb_by"]) {
			$suppWB = trim($_POST["supplier-wb_by"]);
		}

		if ($suppWB == 'create') {
			if (!$_SESSION["SUPPLIER_CREATE_WB"]) {
				$newSupply = date("d.m.Y");

				$res = $wb->createSupplie($newSupply);

				if ($res['id']) {
					$supplierWB = $res['id'];
					$_SESSION["SUPPLIER_CREATE_WB"] = $supplierWB;
				}

			} else {
				$supplierWB = $_SESSION["SUPPLIER_CREATE_WB"];
			}
		} else {
			$supplierWB = $suppWB;
		}
	}

	$logger->log("LOG", "arFilter", [$arFilter]);

	$obj->getPropOrderFlg = true;
	$order = $obj->getOrder(array(), $arFilter);

	$logger->log("LOG", "order", [$order]);

	$cnt = 0;
	//AddMessage2Log($order);
	$arFind = array();
	foreach($order as $key => $arOrder){

		$start = debug_microtime_float();
		//$GLOBALS["START_YP_TIME"] = debug_microtime_float();
		//$GLOBALS["YP_DEBUG"] = array();

		$logger->log("LOG", "arOrder", [$arOrder]);
		$arFind[] = mb_strtolower($arOrder[$prop]);
		$arFindOrderID[] = $arOrder["ORDER_ID"];
//

		//$res = $obj->setStatusOrder($arOrder["ID"], $_POST["status"]);
		if ( $source == 'yandex'){
			$timeLog = [
				'ORDER_ID' => $arOrder["ID"],
				'ORDER_STATUS' => $_POST['status'],
				'TIMESTART' => date('Y.m.d G:i:s'),
				'PROCESS_STATUS' => "STATUS CHANGE START"
			];
			$logger->log("LOG", "timeLog", [$timeLog]);
			$bitrixOrder = \Bitrix\Sale\Order::load( $arOrder['ID'] );

			$timeLog = [
				'ORDER_ID' => $arOrder["ID"],
				'ORDER_STATUS' => $_POST['status'],
				'TIMESTART' => date('Y.m.d G:i:s'),
				'PROCESS_STATUS' => "GOT ORDER"
			];
			$logger->log("LOG", "timeLog2", [$timeLog]);
			$bitrixOrder->setField('STATUS_ID', $_POST['status']);

			$timeLog = [
				'ORDER_ID' => $arOrder["ID"],
				'ORDER_STATUS' => $_POST['status'],
				'TIMESTART' => date('Y.m.d G:i:s'),
				'PROCESS_STATUS' => "FIELD SETTED"
			];
			$logger->log("LOG", "timeLog3", [$timeLog]);

			$subResult = $bitrixOrder->save();
			$timeLog = [
				'ORDER_ID' => $arOrder["ID"],
				'ORDER_STATUS' => $_POST['status'],
				'TIMESTART' => date('Y.m.d G:i:s'),
				'PROCESS_STATUS' => "STATUS CHANGE END"
			];
			$res = $subResult->isSuccess();
		}else{
			$res = OrderService::setStatusOrderD7($arOrder["ID"], $_POST["status"]);
		}



		if($res === false){
			$arResult["ERROR"][] = "<p style='color:red'>{$arOrder["ORDER_ID"]} не удалось установить статус {$arResult["STATUS"][$_POST["status"]]["NAME"]}</p>";
			$arResult["LOG"][$arOrder["ORDER_ID"]] = array(
				"TEXT" => "Заказ {$arOrder["ORDER_ID"]} не удалось установить статус {$arResult["STATUS"][$_POST["status"]]["NAME"]}",
				"STATUS" => "ERROR",
			);
		}else{
			$_SESSION["STATUS_ORDER_CNT"]++;
			$arResult["LOG"][$arOrder["ORDER_ID"]] = array(
				"TEXT" => "Заказ {$arOrder["ORDER_ID"]} {$arResult["STATUS"][$_POST["status"]]["NAME"]}",
				"STATUS" => "OK",
			);

			if(in_array($source, ['wb', 'wb_tl', 'wb_by']) && $_POST["status"] == "F" && $supplierWB){
				$ar = [
					intval($arOrder["MAXYSS_WB_NUMBER"])
				];
				$resWB = $wb->orderToSupplie($supplierWB, $ar);
				$logger->log("LOG", "resWB", [$resWB, $supplierWB, $ar]);
			}
		}
		$end = debug_microtime_float() - $start;
		$end = round($end, 2);
		//$arResult["ERROR"][] = "<p style='color:red'>{$arOrder["ORDER_ID"]} - {$end} с.</p>";
		/*foreach($GLOBALS["YP_DEBUG"] as $k => $er){
			$arResult["ERROR"][] = "<p style='color:red'>{$er}</p>";
		}*/
	}
//AddMessage2Log($arFindOrderID);

	//смотрим какой статус и надо ли отправлять в МС
	$arResult["SEND_MS"] = false;
	$arResult["ORDER_STATUS_SEND_MS"] = json_decode(CProSet::getOption("ORDER_STATUS_SEND_MS"), true);
	if(in_array_($_POST["status"], $arResult["ORDER_STATUS_SEND_MS"]["DEMAND"])){
		$arResult["SEND_MS"] = true;
		$arResult["MS_ACTION"] = "demand";
	}elseif(in_array_($_POST["status"], $arResult["ORDER_STATUS_SEND_MS"]["SALES_RETURN"])){
		$arResult["SEND_MS"] = true;
		$arResult["MS_ACTION"] = "salesreturn";
	}

	// $arResult["SEND_MS"] = false;
	if($arResult["SEND_MS"] === true){
		$objMS = new MoyskladAPI($cabinet);
		//отправляем в MS для создания отгрузок

		if(is_array($arFindOrderID) && count($arFindOrderID) > 0){
			//смотрим заказы в МС
			$strSql = "SELECT * FROM ci_ms_order WHERE ORDER_NUMBER IN ('" . implode("','", $arFindOrderID) . "') AND SITE_ID = '{$cabinet}'";

			$results = $DB->Query($strSql, false, $err_mess.__LINE__);

			while ($row = $results->Fetch()){
				$arResult["ORDER_MS"][$row["ORDER_NUMBER"]] = $row;
			}
			$c1 = (is_array($arResult["ORDER_MS"]) ? count($arResult["ORDER_MS"]) : 0);
			$c2 = (is_array($arFindOrderID) ? count($arFindOrderID) : 0);
			if($c1 != $c2){
				foreach($arFindOrderID as $find){
					if(!$arResult["ORDER_MS"][$find]){
						$arResult["ERROR"][] = "<p style='color:red;'>MS. {$find} - не найден</p>";
						$arResult["LOG"][$find]["TEXT"] .= " {$find} - MS. Заказ не найден для отправки";
						$arResult["LOG"][$find]["STATUS"] = "ERROR";
					}
				}
			}

			if(is_array($arResult["ORDER_MS"]) && count($arResult["ORDER_MS"]) > 0){
				$arSend = array();
				foreach($arResult["ORDER_MS"] as $key => $arItem){

					$resOrder = $objMS->customRequest("https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$arItem["MS_ID"]}");

					if(!$resOrder || $resOrder["errors"]){
						$arResult["ERROR"][] = "<p style='color:red;'>MS. Данные по заказу {$arItem["ORDER_NUMBER"]} не получены</p>";

						$arResult["LOG"][$arItem["ORDER_NUMBER"]]["TEXT"] .= " MS. Данные по заказу {$arItem["ORDER_NUMBER"]} не получены";
						$arResult["LOG"][$arItem["ORDER_NUMBER"]]["STATUS"] = "ERROR";
						continue;
					}
					if($arResult["MS_ACTION"] == "demand" && $resOrder["demands"] && count($resOrder["demands"]) > 0){
						$arResult["ERROR"][] = "<p style='color:red;'>MS. По заказу {$arItem["ORDER_NUMBER"]} уже есть отгрузка</p>";

						$arResult["LOG"][$arItem["ORDER_NUMBER"]]["TEXT"] .= " {$arItem["ORDER_NUMBER"]} - MS. Уже есть отгрузка";
						$arResult["LOG"][$arItem["ORDER_NUMBER"]]["STATUS"] = "ERROR";
						insertDocMS(array("ORDER_NUMBER" => $arItem["ORDER_NUMBER"], "STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"], "TYPE" => "DEMAND"));
						continue;
					}

					if($arResult["MS_ACTION"] == "demand"){
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
										foreach($arMS["errors"] as $k => $v){
											$arResult["ERROR"][] = "<p style='color:red;'>MS. Заказ - {$key}. {$v["error"]}</p>";

											$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. {$v["error"]}";
											$arResult["LOG"][$key]["STATUS"] = "ERROR";

										}
									}elseif($arMS["error"]){
										$arResult["ERROR"][] = "<p style='color:red;'>MS. Заказ - {$key}. {$arMS["error"]}</p>";

										$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. {$arMS["error"]}";
										$arResult["LOG"][$key]["STATUS"] = "ERROR";
									}else{
										$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Ошибка не определена";
									}
								}else{
									$_SESSION["STATUS_MS_ACTION_CNT"]++;
									$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Создана отгрузка";
									insertDocMS(array(
										"ORDER_NUMBER" => $arItem["ORDER_NUMBER"],
										"STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"],
										"TYPE" => "DEMAND")
									);
								}
							}
						}else{
							$arResult["ERROR"][] = "<p style='color:red;'>MS. Заказ - {$key}. Шаблон создания не удалось получить</p>";
							$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Шаблон создания не удалось получить";
							$arResult["LOG"][$key]["STATUS"] = "ERROR";
						}
					}elseif($arResult["MS_ACTION"] == "salesreturn"){

						if($resOrder["demands"]){
							$demand = $resOrder["demands"][0];
							//смотрим есть ли возврат
							$resDemand = $objMS->customRequest($demand["meta"]["href"]);
							if($resDemand["returns"] && count($resDemand["returns"]) > 0){
								$arResult["ERROR"][] = "<p style='color:red;'>MS. По заказу {$arItem["ORDER_NUMBER"]} уже есть возврат</p>";

								$arResult["LOG"][$arItem["ORDER_NUMBER"]]["TEXT"] .= " {$arItem["ORDER_NUMBER"]} - MS. Уже есть возврат";
								$arResult["LOG"][$arItem["ORDER_NUMBER"]]["STATUS"] = "ERROR";
								insertDocMS(array(
									"ORDER_NUMBER" => $arItem["ORDER_NUMBER"],
									"STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"],
									"TYPE" => "SALES_RETURN")
								);
								continue;
							}else{

								$arTemplate = $objMS->getSalesReturnTemplate($demand["meta"]);

								if($arTemplate["demand"]){

									if ($_POST['warehouse'] == '--- Выберите склад ---') {
										$arResult["ERROR"][] = "<p style='color:red;'>Выберите склад!</p>";
										$output = Array('offset' => 0, 'sucsess' => 1, 'error' => $arResult["ERROR"], 'info' => '');

										echo json_encode($output, JSON_UNESCAPED_UNICODE);

										header('Content-Type: application/json;charset=UTF-8');
										die();
									} else {
										$whId = $_POST['warehouse'];

										$arTemplate['store'] = array('meta' => array(
														"href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/{$whId}",
														"metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/store/metadata",
														"type" => "store",
														"mediaType" => "application/json",
														"uuidHref" => "https://online.moysklad.ru/app/#warehouse/edit?id={$whId}"
										));

										$resMS = $objMS->setSalesReturn(array($arTemplate));


										foreach($resMS as $k => $arMS){
											if(!$arMS["id"]){
												if($arMS["errors"]){
													foreach($arMS["errors"] as $k => $v){
														$arResult["ERROR"][] = "<p style='color:red;'>MS. Возврат. Заказ - {$key}. {$v["error"]}</p>";

														$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. {$v["error"]}";
														$arResult["LOG"][$key]["STATUS"] = "ERROR";

													}
												}elseif($arMS["error"]){
													$arResult["ERROR"][] = "<p style='color:red;'>MS. Возврат. Заказ - {$key}. {$arMS["error"]}</p>";

													$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. {$arMS["error"]}";
													$arResult["LOG"][$key]["STATUS"] = "ERROR";
												}else{
													$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. Ошибка не определена";
												}
											}else{
												$_SESSION["STATUS_MS_ACTION_CNT"]++;
												$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Создан возврат";
												insertDocMS(array("ORDER_NUMBER" => $arItem["ORDER_NUMBER"], "STATUS" => $arOrder[$arItem["ORDER_NUMBER"]]["STATUS_ID"], "TYPE" => "SALES_RETURN"));
											}
										}
									}
								}else{
									$arResult["ERROR"][] = "<p style='color:red;'>MS. Возврат. Заказ - {$key}. Шаблон создания не удалось получить</p>";
									$arResult["LOG"][$key]["TEXT"] .= " {$key} - MS. Возврат. Шаблон создания не удалось получить";
									$arResult["LOG"][$key]["STATUS"] = "ERROR";
								}

							}
						}else{
							$arResult["ERROR"][] = "<p style='color:red;'>MS. По заказу {$arItem["ORDER_NUMBER"]} нет отгрузки</p>";

							$arResult["LOG"][$arItem["ORDER_NUMBER"]]["TEXT"] .= " MS. {$arItem["ORDER_NUMBER"]} - нет отгрузки для создания возврата";
							$arResult["LOG"][$arItem["ORDER_NUMBER"]]["STATUS"] = "ERROR";
						}
					}

				}

			}

		}else{
			$arResult["ERROR"][] = "<p style='color:red'>Не найдены номера заказов</p>";
		}

	}

	$c1 = (is_array($arListFilter) ? count($arListFilter) : 0);
	$c2 = (is_array($arFind) ? count($arFind) : 0);

	$logger->log("LOG", "arFind", [$arFind, $arListFilter]);

	if($c1 != $c2){
		foreach($arListFilter as $find){

			if(!in_array_(mb_strtolower($find), $arFind)){
				$arResult["INFO"][] = "<p style='color:red;'>{$find} - не найден</p>";
			}
		}
	}
}


// Проверяем, все ли строки обработаны
$offset = $offset + $step;

//AddMessage2Log($arFilter);
/*

$cnt_all = count($order);
echo "<p style=''>Всех заказов {$cnt_all}. Статус изменен у {$cnt}</p>";
*/


if ($offset >= $count || !$isAllowed) {
	$sucsess = 1;

	$arCount = array_count_values ($arList);
	foreach($arCount as $val => $cnt){
		if($cnt > 1){
			$arResult["INFO"][] = "<p style='color:red;'>{$val} - количество повторений {$cnt}</p>";
		}
	}
	$arResult["INFO"][] = "<p>Всех элементов в списке - " . count($arList) . ". Установлено для {$_SESSION["STATUS_ORDER_CNT"]}</p>";

	if($arResult["SEND_MS"] === true){
		$arResult["INFO"][] = "<p>Создано " . ($arResult["MS_ACTION"] == "demand" ? "отгрузок" : "возвратов") . " в MS - {$_SESSION["STATUS_MS_ACTION_CNT"]}</p>";
	}

} else {
	$sucsess = round($offset / $count, 2);
}
//$buffer = ob_get_flush();
//AddMessage2Log($buffer);


ob_end_clean();
// И возвращаем клиенту данные (номер итерации и сообщение об окончании работы скрипта)
$output = Array('offset' => $offset, 'sucsess' => $sucsess, 'error' => $arResult["ERROR"], 'info' => $arResult["INFO"]);

$arLog = array(
	"DATE" => date("Y-m-d H:i:s"),
	"arResult" => $arResult,
	"USER_ID" => $USER->getID(),
);
$logger->log("LOG", "arResult", [$arLog]);

//общий лог

foreach($arResult["LOG"] as $key => $arItem){
	if($arItem["STATUS"] == "OK"){
		$txt = "<p>{$arItem["TEXT"]}</p>";
	}else{
		$txt = "<p style='color:red;'>{$arItem["TEXT"]}</p>";
	}
	$arLog = array(
		"DATE" => date("Y-m-d H:i:s"),
		"TEXT" => $txt,
		"USER_ID" => $USER->getID(),
	);
	file_put_contents("/home/bitrix/logs/utils_set_status_order.txt", serialize($arLog) . "\r\n", FILE_APPEND | LOCK_EX);
}


echo json_encode($output, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
