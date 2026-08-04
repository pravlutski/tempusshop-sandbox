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

$data_string = json_encode(array('count' => 5000));

for ($i=0; $i < 8; $i++) {
	$ch = curl_init('https://content-api.wildberries.ru/content/v2/barcodes');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Authorization: ' . $token,
	));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	$res = curl_exec($ch);
	curl_close($ch);

	$res = json_decode($res, true);
	$data = $res['data'];
	file_put_contents('codes.txt', implode(PHP_EOL, $data).PHP_EOL,FILE_APPEND);
	sleep(1);
}
