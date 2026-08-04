<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
// Отвечаем только на Ajax
//if ($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {return;}

if(!CModule::IncludeModule('panel.manager'))return;
CModule::IncludeModule("maxyss.wb");
global $USER;

global $DB;
//tmp



// Можно передавать в скрипт разный action и в соответствии с ним выполнять разные действия.
$fileDataSales = "/var/www/bitrix/data/www/logs/ms/tempPurchase/CheckSalesCount/" . $USER->getID() . ".txt";


$arResult["LOGIN"] = array('s1', 'msk');
$arResult["ERROR"] = [];

foreach ($arResult["LOGIN"] as $key => $login) {

		$fileData = "/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/".$login."/purchase/".$_POST["date_from"]."%".$_POST["date_to"].".txt";
		$fileDataLog = "/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/".$login."/purchase/LOG_".$_POST["date_from"]."%".$_POST["date_to"].".txt";
		$ms = new MoyskladAPI($login);

		$action = $_REQUEST['action'];

		if (empty($action)) {return;}

		$momentFrom = $_REQUEST["date_from"];
		$momentTo = $_REQUEST["date_to"];

		if(empty($_REQUEST["date_pre"])) {
			$arResult["ERROR"][][] = "<p style='color:red;'>Заполните расчетную дату!</p>";
		}


		$dateDiff = floor((strtotime($momentTo) - strtotime($momentFrom)) / (60 * 60 * 24));

		if($dateDiff <= 0){
			$arResult["ERROR"][][] = "<p style='color:red;'>Заполните корректные даты ОТ-ДО</p>";
		}

		if($action == "stop"){
			$arResult["ERROR"][] = "<p style='color:red;'>Отмена</p>";
		}

		if(count($arResult["ERROR"])){
			$output = Array('offset' => 0, 'sucsess' => 1, 'error' => $arResult["ERROR"]);

			echo json_encode($output, JSON_UNESCAPED_UNICODE);

			header('Content-Type: application/json;charset=UTF-8');
			die();
		}

		$limit = 1000;

		// Получаем от клиента номер итерации
		$offset = intval($_REQUEST['offset']);

		// очищаем на первой итерации
		if($offset == 0){
			file_put_contents($fileData, $momentFrom . ";" . $momentTo . "\r\n");
			//file_put_contents($fileDataLog, $momentFrom . ";" . $momentTo . "\r\n");
		}

		$data = $ms->customRequest("https://api.moysklad.ru/api/remap/1.2/report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}");

		/*$fileData2 = "/var/www/bitrix/data/www/logs/ms/tempPurchase/log.txt";
		file_put_contents($fileData2, print_r($offset, true) . "\r\n", FILE_APPEND);
		file_put_contents($fileData2, print_r($limit, true) . "\r\n", FILE_APPEND);
		file_put_contents($fileData2, print_r($data, true) . "\r\n", FILE_APPEND);
		*/
		$count = intval($data["meta"]["size"]);

		//$step = 1000;

		ob_start();
		//prent($data["rows"]);
		if(is_array($data["rows"]) && count($data["rows"]) > 0){
			// собираем массив всех данных
			//file_put_contents($fileDataLog, print_r($data["rows"], true) . "\r\n", FILE_APPEND);
			$str = base64_encode(serialize($data["rows"]));
			file_put_contents($fileData, $str . "\r\n", FILE_APPEND);
		}


		// Проверяем, все ли строки обработаны
		$offset = $offset + $limit;

		if ($offset >= $count) {
			$sucsess = 1;
		} else {
			$sucsess = round($offset / $count, 2);
		}

		ob_end_clean();
}
// И возвращаем клиенту данные (номер итерации и сообщение об окончании работы скрипта)
$output = Array('offset' => $offset, 'sucsess' => $sucsess, 'error' => $arResult["ERROR"]);

echo json_encode($output, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
