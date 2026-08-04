#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
// Обновляем товары которые изменились при загрузках прайсов.
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);
global $DB;
CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule("intaro.retailcrm");

RCrmActions::orderAgent();
die; 


$ms = new MoyskladAPI("s1");

$period = new DatePeriod(
	new DateTime('16.04.2022'),
	new DateInterval('P1D'),
	new DateTime('16.04.2023 23:59')
);
 
$dates = array();
foreach ($period as $key => $value) {
	$dates[] = $value->format('Y-m-d');     
}

foreach($dates as $date){
	$arFilter = array(
		"momentFrom" => "{$date} 00:00:01",
		"momentTo" => "{$date} 23:59:59",
	);
	$res = $ms->getListProfitChannel($arFilter);
	
	file_put_contents("/home/bitrix/logs/ms/profit_channel/{$date}.txt", serialize($res));
}
die;



/*
			"E" => "Обновление складов",
			"Y" => "Выгрузка яндекса",
			"O" => "Обновление онлайнера",
			"U" => "Обновление каталога",
			"C" => "Загрузка каталога онлайнера",
			"UI" => "Обновление цены у товара",
			"P" => "Парсер отзывов с яндекса",
			"PP" => "Парсер цен с яндекса",
			"PC" => "Парсер цен ceneo",
			"OR" => "Изменения в заказах",
			"DD" => "Обновление значений в таблице сроков поставки",
			"R" => "Разное",
			"S" => "Обновление СУПЕРЦЕНА",
			"YP" => "YPartner",
			"ER" => "Ошибки",
			"MC" => "Обмен с MoySklad",
			"WB" => "WB",
*/
$strSql = "SELECT event,detail,text,id FROM ci_log WHERE event = 'UI'"; 
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	if(strlen($row["detail"]) > 0){
		$f = uniqid();
		

		file_put_contents("/home/bitrix/logs/detail/{$f}.txt", $row["detail"]);
		$search = "";
		if($row["event"] == "UI"){
			$search = strip_tags($row["text"]);
		}elseif($row["event"] == "WB"){
			//$r = unserialize($row["detail"], ['allowed_classes' => false]);
			//$search = strip_tags($r["res"]);
			//$search = str_replace(array("Товар выгружен", "Картинки выгружены ", "Article "), " ", $search);
			//prent($search);
		}else{
			$search = strip_tags($row["detail"]);
			$search = str_replace(array("Удалена модель -", "Добавлена модель -", "Есть в заказе -"), "", $search);
			$search = str_replace(array("\r\n", "\r", "\n", "   ", "  ", "; "), " ", $search);
		}
		
		$in = array("detail" => false, "file_id" => "'{$f}'", "search" => "'".addslashes($search)."'");
		prent($in,0,1);
		$DB->Update("ci_log", $in, "WHERE id = '".$row["id"]."'", $err_mess.__LINE__);
		
	}
} 
//CCatalogExport::PreGenerateExport(26);
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>