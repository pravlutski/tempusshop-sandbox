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

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/all_cycle_log.txt", print_r('START:' . date('Y-m-d H:i:s'), true) . "\r\n", FILE_APPEND);
CProSet::setOption("WB_ALL_CYCLE_PER", "0");

foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $_REQUEST[$k] = $v;
}

if(in_array($_REQUEST["cabinet"], array("DEFAULT", "WR"))){
	$cabinet = $_REQUEST["cabinet"];
}else{
	$cabinet = "WR";
}

//пишим атрибуты
exec("pgrep -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/set_item_props.php",$output,$code);
if(count($output) > 0){
	foreach($output as $pid)
		exec("kill -9 {$pid}");
}

system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/set_item_props.php >>/home/bitrix/logs/wb/set_item_props.txt");

CProSet::setOption("WB_ALL_CYCLE_PER", "25");
//отсылаем товары
exec("pgrep -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/upload_items.php",$output,$code);
if(count($output) > 0){
	$kill = false;
	foreach($output as $pid){
		exec("ps axu | grep {$pid}",$output2,$code2);

		$flg = false;
		foreach($output2 as $info){

			if(stripos($info, "/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/upload_items.php cabinet=".$cabinet)){
				$flg = true;
				$p = $pid;
			}
		}

		if($flg === true){
			$kill = true;
			exec("kill -9 {$p}");
		}
	}
	if($kill === true){
		CLog::add2log(array("event" => "R", "text" => "Убили 'Загрузить на WB' {$cabinet}"));
	}

}

system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/upload_items.php cabinet=".$cabinet." >>/home/bitrix/logs/wb/upload_items.txt");
/*
CProSet::setOption("WB_ALL_CYCLE_PER", "50");

//запрашиваем склад и пишем в свойство PROP_MAXYSS_NMID_CREATED_WB
exec("pgrep -f /userscripts/wb/update_nmId.php",$output,$code);
if(count($output) > 0){
	foreach($output as $pid)
		exec("kill -9 {$pid}");
}
system("/usr/bin/php81 -f /userscripts/wb/update_nmId.php >>/userscripts/logs/update_nmId.txt");

//запрашиваем и пишем в свойство PROP_MAXYSS_CARDID_WB
exec("pgrep -f /userscripts/wb/update_cardId.php",$output,$code);
if(count($output) > 0){
	foreach($output as $pid)
		exec("kill -9 {$pid}");
}

system("/usr/bin/php81 -f /userscripts/wb/update_cardId.php >>/userscripts/logs/update_cardId.txt");


CProSet::setOption("WB_ALL_CYCLE_PER", "75");
*/
//получаем артикулы
if(count($output) > 0){
	$kill = false;
	foreach($output as $pid){
		exec("ps axu | grep {$pid}",$output2,$code2);

		$flg = false;
		foreach($output2 as $info){

			if(stripos($info, "/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/get_articles.php cabinet=".$cabinet)){
				$flg = true;
				$p = $pid;
			}
		}

		if($flg === true){
			$kill = true;
			exec("kill -9 {$p}");
		}
	}
	if($kill === true){
		CLog::add2log(array("event" => "R", "text" => "Убили 'Получить артикулы WB' {$cabinet}"));
	}

}

system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/get_articles.php cabinet=".$cabinet." >>/home/bitrix/logs/wb/get_articles.txt");

system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/wb_collection.php >>/home/bitrix/logs/wb/log_collection.txt");

CProSet::setOption("WB_ALL_CYCLE_PER", "100");
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/all_cycle_log.txt", print_r('END:' . date('Y-m-d H:i:s'), true) . "\r\n", FILE_APPEND);
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
