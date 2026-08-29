<?php
// Обновляем активность у товаров. если цена на товар не изменялась > 365 дней, то деактивируем, иначе активируем.
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}
set_time_limit(3600);

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");

global $DB;
$el = new CIBlockElement;

$ar = [
	'active', 'active_ru', 'active_by', 'active_ya', 'active_os', 
	'active_wb', 'active_wbtl', 'active_av', 'active_sb', 'active_ozti',
];

$where = [];
foreach ($ar as $col) {
	$where[] = "$col='Y'";
}
$arPrice = array();
$strSql = "SELECT bitrix_id
	FROM ci_price 
	WHERE " . implode(" OR ", $where);

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$flg = false;
while ($row = $results->Fetch()){
	$arPrice[$row["bitrix_id"]] = true;
}

$arDeact = $arActivate = [];

$strSql = "SELECT el.IBLOCK_SECTION_ID AS SECTION,
		cat.PRODUCT_ID as PRODUCT_ID, DATEDIFF(CURDATE(), MAX(cat.TIMESTAMP_X)) as LAST_UPDATE_PRICE, el.ACTIVE as ACTIVE
	FROM
		b_catalog_price cat
	LEFT JOIN
		b_iblock_element el
	ON cat.PRODUCT_ID=el.ID
		GROUP BY cat.PRODUCT_ID";//  LIMIT 0,10

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arDate = [];
$exclude = [2936,1093,1092,4503,1099,1100,3034,3047,3036,3048,3161,3027,3162,3164,3035,3163,3033,3032,406,403,405,404,3168,3166,3167,402,399,400,401,3043,3045,3044,3046,2940,2939,3222,2942,2941,2975,3041,3246,3245,3221,3150];
while ($row = $results->Fetch()){
	if (in_array($row['SECTION'], $exclude)) continue;

	//если больше года, то в массив для деактивации
	if($row["LAST_UPDATE_PRICE"] >= 365){
		if ($arPrice[$row["PRODUCT_ID"]] && $row["ACTIVE"] == "N") {
			$arActivate[] = $row;
		} elseif ($row["ACTIVE"] == "Y") {
			$arDeact[] = $row;
		}
	}elseif($row["LAST_UPDATE_PRICE"] < 365 && $row["ACTIVE"] == "N"){
		$arActivate[] = $row;
	}
}
//prent(array_column($arActivate, 'PRODUCT_ID'),0,1);
//prent(count($arActivate),0,1);
//die;
if(count($arDeact) > 0){
	file_put_contents("/home/bitrix/logs/catalog/update_activity_items/deactivate_items_".date("Y_m_d_H_i_s").".txt", print_r($arDeact, true) . "\r\n", FILE_APPEND);
	foreach($arDeact as $arItem){
		$arLoadProductArray = Array(
			"ACTIVE" => "N",
			"SORT"	=> 1000,
		);
		$rs = $el->Update($arItem["PRODUCT_ID"], $arLoadProductArray);

		$arLog = array(
			"event" => "R",
			"text" => "Деактивировали товар - {$arItem["PRODUCT_ID"]}",
			"detail" => "Нет в наличии - {$arItem["LAST_UPDATE_PRICE"]} дней",
		);
		CLog::add2log($arLog);
	}
}


if(count($arActivate) > 0){
	file_put_contents("/home/bitrix/logs/catalog/update_activity_items/activate_items_".date("Y_m_d_H_i_s").".txt", print_r($arActivate, true) . "\r\n", FILE_APPEND);
	foreach($arActivate as $arItem){
		$arLoadProductArray = Array(
			"ACTIVE" => "Y",
			"SORT"	=> 500,
		);
		$rs = $el->Update($arItem["PRODUCT_ID"], $arLoadProductArray);

		$arLog = array(
			"event" => "R",
			"text" => "Активировали товар - {$arItem["PRODUCT_ID"]}",
			"detail" => "Нет в наличии - {$arItem["LAST_UPDATE_PRICE"]} дней",
		);
		CLog::add2log($arLog);
	}
}
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
