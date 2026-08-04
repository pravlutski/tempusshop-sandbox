<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);
require_once '/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';
//require_once 'importProductsControl.php';
CModule::IncludeModule('panel.manager');
use Bitrix\Main\Application,
	Bitrix\Main\Loader;


$CurDB = new DBPanel();


$strSql = "SELECT * FROM wdhs_wb_main_settings";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arSetting[$row['cabinet']] = $row;
}
$_REQUEST["CABINET"] = 'WR';
$clientId = $arSetting[$_REQUEST["CABINET"]]['clientId'];
$token = $arSetting[$_REQUEST["CABINET"]]['api'];
$settings = json_decode($arSetting[$_REQUEST["CABINET"]]['settings'],true);


$ch = curl_init('https://seller-analytics-api.wildberries.ru/api/v1/warehouse_remains?groupBySa=true');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	'Content-Type: application/json',
	'Authorization: ' . $token,
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
$res = curl_exec($ch);
curl_close($ch);

$res = json_decode($res, true);
print_r('create');
print_r($res);

$taskid = $res['data']['taskId'];

unset($res);
// $taskid = '8082f045-fec9-491a-b958-5a3ce5bf6fbd';

 $ch = curl_init('https://seller-analytics-api.wildberries.ru/api/v1/warehouse_remains/tasks/'.$taskid.'/download');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	'Content-Type: application/json',
	'Authorization: ' . $token,
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
$res = curl_exec($ch);
curl_close($ch);

$res = json_decode($res, true);
print_r('get');
print_r($res);
$arResult = [];
foreach ($res as $key => $value) {
	if (count($value['warehouses']) > 0) {
		foreach ($value['warehouses'] as $wh) {
			if (isset($arResult[$value['vendorCode']])) {
				$arResult[$value['vendorCode']] = intval($arResult[$value['vendorCode']]) + intval($wh['quantity']);
			} else {
				$arResult[$value['vendorCode']] = intval($wh['quantity']);
			}
		}
	}
}

$CurDB->Query("DELETE FROM wb_report_fbo WHERE 1=1", false, $err_mess.__LINE__);

foreach ($arResult as $k=> $v) {
	$in = array(
		"model" => "'".$k."'",
		"quantity" => "'".$v."'",
	);

	$fields = implode(",", array_keys($in));
	$values = implode(",",$in);

	$sql = "INSERT INTO wb_report_fbo ($fields) VALUES ($values)";
	$CurDB->query($sql);
}
