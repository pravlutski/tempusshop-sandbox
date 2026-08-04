#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//  переводит заказы в Выполнен, Выполнен без смс , если больше 3 дней в "Передан в службу доставки"
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);
//if (function_exists('ini_set')) ini_set('memory_limit','1512M');
		
CModule::IncludeModule("iblock");
CModule::IncludeModule("main");

list($usec, $sec) = explode(" ", microtime());
$time_start = ((float)$usec + (float)$sec);

if(CModule::IncludeModule("panel.manager")){
	
	global $DB;
	
	$strSql = "SELECT ord.ID as ORDER_ID, ch.DATA, ch.DATE_CREATE, deliv.DELIVERY_ID
	FROM b_sale_order ord 
	LEFT JOIN b_sale_order_change ch ON ord.ID=ch.ORDER_ID 
	LEFT JOIN b_sale_order_delivery deliv ON ch.ORDER_ID=deliv.ORDER_ID 
	WHERE ord.LID = 's2' 
	AND ord.STATUS_ID = 'Pr' AND ord.CANCELED = 'N' AND 
	ch.TYPE = 'ORDER_STATUS_CHANGED' AND ch.DATE_CREATE < (CURDATE() - 3) ORDER BY ch.DATE_CREATE ASC";
	
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arModify = unserialize($row["DATA"]);
		if($arModify["STATUS_ID"] == "Pr"){
			$arResult["ORDER"][$row["ORDER_ID"]] = array(
				"ORDER_ID" => $row["ORDER_ID"],
				"DATA" => unserialize($row["DATA"]),
				"DATE_CREATE" => $row["DATE_CREATE"],
				"DELIVERY_ID" => $row["DELIVERY_ID"],
			);
		}
	}
	
	foreach($arResult["ORDER"] as $key => $arItem){
		if($arItem["DELIVERY_ID"] == 20){
			OrderService::setStatusOrder($arItem["ORDER_ID"], "FB");
			$txt = "Статус заказа <a href='/bitrix/admin/sale_order_view.php?ID={$arItem["ORDER_ID"]}' target='_blank'>{$arItem["ORDER_ID"]}</a> изменен на 'Выполнен, без смс'";
		}else{
			OrderService::setStatusOrder($arItem["ORDER_ID"], "F");
			$txt = "Статус заказа <a href='/bitrix/admin/sale_order_view.php?ID={$arItem["ORDER_ID"]}' target='_blank'>{$arItem["ORDER_ID"]}</a> изменен на 'Выполнен'";
		}

		CLog::add2log(array("event" => "OR", "text" => $txt));
	}
	//prent($arResult["ORDER"]);
	//unset($arItem);
}
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>