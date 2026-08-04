#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
// Обновляем товары которые изменились при загрузках прайсов.
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(1800);
global $DB;
CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");

$filepath = $_SERVER["DOCUMENT_ROOT"] . "/upload/ms/profit_items.csv";
$fileProgress = $_SERVER["DOCUMENT_ROOT"] . "/local/cron/parser/progress_1.lock";

$settings = [];
// смотрим настройки 
$strSql = "SELECT * FROM bq_exchange WHERE ID = '1'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	$settings = unserialize($row["SETTINGS"]);
}

if(!$settings["COLUMN"]){
	$settings["COLUMN"] = BQ_EXCHANGE[1]["COLUMN"];
}

$ms = new MoyskladAPI("s1");

switch($settings["PERIOD"]){
	case "1month":
		$from = (new DateTime('-1 month'))->format('d.m.Y');
		break;
	case "2month":
		$from = (new DateTime('-2 month'))->format('d.m.Y');
		break;
	case "1year":
		$from = (new DateTime('-1 year'))->format('d.m.Y');
		break;
	case "all":
		$from = "22.01.2020";
		break;
	default:
		$from = (new DateTime('-1 month'))->format('d.m.Y');
		break;
}
//$from = "2020-01-22";
$period = new DatePeriod(
	new DateTime($from),
	new DateInterval('P1D'),
	new DateTime(date("d.m.Y") . ' 23:59')
);
 
$dates = array();
foreach ($period as $key => $value) {
	$dates[] = $value->format('Y-m-d');     
}

$error = false;

$ctnAll = count($dates);
$i = 0;
foreach($dates as $date){
	$arFilter = array(
		"momentFrom" => "{$date} 00:00:00",
		"momentTo" => "{$date} 23:59:59",
	);
	$res = $ms->getListProfitDay($arFilter);
	if(is_array($res)){
		file_put_contents("/home/bitrix/logs/ms/profit_days/{$date}.txt", serialize($res));
	}else{
		$error = true;
		file_put_contents($fileProgress, "Ошибка при запросе {$date}");
		break;
	}
	$i++;
	$percent = round(($i / $ctnAll) * 100, 2);
	file_put_contents($fileProgress, $percent);
}

if($error === true){
	file_put_contents($filepath, '');
	die;
}

foreach($dates as $date){
	$file = "/home/bitrix/logs/ms/profit_days/{$date}.txt";
	if(file_exists($file)){
		$res  = file_get_contents($file);
		$res  = unserialize($res, ['allowed_classes' => false]);
		
		foreach($res as $k => $v){
			$arCsv[] = [
				"date" => '"'.$date . '"',
				"productName" => '"'.$v["assortment"]["name"] . '"',
				"productXmlID" => '"'.$v["assortment"]["code"] . '"',
				"productArticle" => '"'.$v["assortment"]["article"] . '"',
				"sellQuantity" => '"'.$v["sellQuantity"] . '"',
				"sellPrice" => '"'.$v["sellPrice"] . '"',
				"sellCost" => '"'.$v["sellCost"] . '"',
				"sellSum" => '"'.$v["sellSum"] . '"',
				"sellCostSum" => '"'.$v["sellCostSum"] . '"',
				"returnQuantity" => '"'.$v["returnQuantity"] . '"',
				"returnPrice" => '"'.$v["returnPrice"] . '"',
				"returnCost" => '"'.$v["returnCost"] . '"',
				"returnSum" => '"'.$v["returnSum"] . '"',
				"returnCostSum" => '"'.$v["returnCostSum"] . '"',
				"profit" => '"'.$v["profit"] . '"',
				"margin" => '"'.$v["margin"] . '"',
			];
		}
	}
	
}

unlink($filepath);
//$handle = fopen($filepath, "r");

$str_csv = implode(",", $settings["COLUMN"]) . "\r\n";
file_put_contents($filepath , $str_csv, FILE_APPEND);

foreach($arCsv as $k => $arItem){
	$ar = [];
	foreach($settings["COLUMN"] as $col){
		$ar[] = $arItem[$col];
	}
	$str_csv = implode(",", $ar) . "\r\n";
	file_put_contents($filepath , $str_csv, FILE_APPEND);
}

?>