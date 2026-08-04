<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
// Отвечаем только на Ajax
//if ($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {return;}

if(!CModule::IncludeModule('panel.manager'))return;
CModule::IncludeModule("maxyss.wb");
global $USER;

global $DB;
//tmp

$arResult["ERROR"] = [];

$arResult["LOGIN"] = array('s1', 'msk');

$momentFrom = $_REQUEST["date_from"];
$momentTo = $_REQUEST["date_to"];
$from = new DateTime($momentFrom);
$to = new DateTime($momentTo);
$to->modify('+1 day');
$period = new DatePeriod($from, new DateInterval('P1D'), $to);
$arrayOfDates = array_map(
		function($item){return $item->format('Y-m-d');},
		iterator_to_array($period)
);

$login = 'msk';

$ms = new MoyskladAPI($login);
$action = $_REQUEST['action'];


$date = date("Y-m-d");
$fileDataOst = "/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/".$login."/ost/".$date.".txt";


$fileDataDif = "/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/".$login."/dif/".$_POST["date_from"]."%".$_POST["date_to"].".txt";
$resDif = $ms->customRequest("https://api.moysklad.ru/api/remap/1.2/report/turnover/all?momentFrom={$momentFrom}&momentTo={$momentTo}&filter=agent=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/ccaa773a-1073-11ee-0a80-10c50002f8eb");

//file_put_contents('/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/msk/ost/log.txt', print_r($date, true) . "\r\n", FILE_APPEND);
//file_put_contents('/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/msk/ost/log.txt', print_r($resStock, true) . "\r\n", FILE_APPEND);

if (count($resDif['rows']) > 0) {
	foreach ($resDif['rows'] as $k => $v) {
			if ($v['outcome']['quantity'] > 0) {
				if (isset($arDiff[$v['assortment']['name']])) {
      			$arDiff[$v['assortment']['name']] = $arDiff[$v['assortment']['name']] + intval($v['outcome']['quantity']);
        } else {
            $arDiff[$v['assortment']['name']] = intval($v['outcome']['quantity']);
        }
			}
	}
}
$strDif = json_encode($arDiff);
file_put_contents($fileDataDif, $strDif);



$store = '&store=https://api.moysklad.ru/api/remap/1.2/entity/store/83c00532-0f74-11ee-0a80-143a0014a102;store=https://api.moysklad.ru/api/remap/1.2/entity/store/2bcc228b-173a-11ee-0a80-0fdd000ddd83';
//file_put_contents('/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/msk/ost/log.txt', print_r($store, true) . "\r\n", FILE_APPEND);

$resStock = $ms->customRequest("https://api.moysklad.ru/api/remap/1.2/report/stock/all?filter=moment={$date}+23:59:59{$store}");
//file_put_contents('/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/msk/ost/log.txt', print_r($date, true) . "\r\n", FILE_APPEND);
//file_put_contents('/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/msk/ost/log.txt', print_r($resStock, true) . "\r\n", FILE_APPEND);
if (count($resStock['rows']) > 0) {
	//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/purchase_opt/log.txt', print_r($resStock['rows'], true) . "\r\n", FILE_APPEND);
	foreach ($resStock['rows'] as $k => $v) {
		if ($v['name'] != 'Коробки' or $v['name'] != 'Коробка') {
			$arStockDays[$v['article']] = $v['stock'];
		}
	}
}

$strOst = json_encode($arStockDays);
file_put_contents($fileDataOst, $strOst);


$output = Array('sucsess' => 1);

echo json_encode($output, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
