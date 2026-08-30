#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
// 
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("other_subscribe_product_send_email_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(0);

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("catalog");
CModule::IncludeModule("aspro.max");

$arResult = array();
$arFilter = Array(
	"IBLOCK_ID"	=> CProSet::IB_SUBSCRIBE_PRODUCT,
	"ACTIVE"	=> "Y",
	"!PROPERTY_EMAIL" => false,
);
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "PROPERTY_EMAIL", "PROPERTY_SITE_ID", "PROPERTY_PRODUCT_ID", "PROPERTY_CNT_ATTEMPTS"));
while($ar = $rs->GetNext()){
	if(in_array($ar['PROPERTY_SITE_ID_VALUE'], array("s1", "s2"))){
		
		$type_price = ($ar['PROPERTY_SITE_ID_VALUE'] == "s1" ? 1 : 2);
		$arFilterEl = Array(
			"IBLOCK_ID"	=> CProSet::IB_CATALOG,
			"ACTIVE"	=> "Y",
			"ID"		=> $ar["PROPERTY_PRODUCT_ID_VALUE"],
			">CATALOG_QUANTITY" => 0,
			">CATALOG_PRICE_" . $type_price => 0,
		);

		$rsEl = CIBlockElement::GetList(array(), $arFilterEl, false, false, array("ID", "NAME", "IBLOCK_ID", "DETAIL_PAGE_URL", "PROPERTY_AVAILABILITY_BY", "PROPERTY_AVAILABILITY_RU"));
		if($arFields = $rsEl->GetNext()){
			$arResult["ITEMS"][] = array(
				"ID" => $ar["ID"],
				"PRODUCT_ID" => $arFields["ID"],
				"DETAIL_PAGE_URL" => $arFields["DETAIL_PAGE_URL"],
				"NAME" => $arFields["NAME"],
				"SITE_ID" => $ar["PROPERTY_SITE_ID_VALUE"],
				"EMAIL" => $ar["PROPERTY_EMAIL_VALUE"],
				"CNT_ATTEMPTS" => $ar["PROPERTY_CNT_ATTEMPTS_VALUE"],
				"AVAILABILITY_BY" => $arFields["PROPERTY_AVAILABILITY_BY_ENUM_ID"],
				"AVAILABILITY_RU" => $arFields["PROPERTY_AVAILABILITY_RU_ENUM_ID"],
			);

		}
	}
}
//prent($arResult["ITEMS"],0,1);
if(count($arResult["ITEMS"]) > 0){
	foreach($arResult["ITEMS"] as $key => $arItem){
		
		if(($arItem["SITE_ID"] == "s1" && $arItem["AVAILABILITY_RU"] != 512) || ($arItem["SITE_ID"] == "s2" && !in_array($arItem["AVAILABILITY_BY"], array(492,493)))){
			continue;
		}
		
		if($arItem["SITE_ID"] == "s1"){
			$message = "Товар <a href='https://tempusshop.ru{$arItem["DETAIL_PAGE_URL"]}' target='_blank'>{$arItem["NAME"]}</a> поступил на склад и доступен для заказа. Заказ можно оформить по телефону +7(800)500-65-42 или на сайте tempusshop.ru";
			$log_text = "Подписка на товар - {$arItem["NAME"]}. Сообщение успешно отправлено - " . $arItem["EMAIL"];
		}elseif($arItem["SITE_ID"] == "s2"){
			$message = "Товар <a href='https://tempus.by{$arItem["DETAIL_PAGE_URL"]}' target='_blank'>{$arItem["NAME"]}</a> поступил на склад и доступен для заказа. Заказ можно оформить по телефону +375293449966 или на сайте www.tempus.by";
			$log_text = "Подписка на товар - {$arItem["NAME"]}. Сообщение успешно отправлено - " . $arItem["EMAIL"];
		}
		
		$arEventFields = array(
			"EMAIL_RAW"	=> $arItem["EMAIL"],
			"PRODUCT_NAME"	=> $name,
			"MESSAGE"	=> $message,
			"SUBJECT"	=> "Товар {$arItem["NAME"]} поступил на склад и доступен для заказа",
		);
					
		CEvent::Send("FORM_FILLING_ADDTOSUBSCRIBE", $arItem["SITE_ID"], $arEventFields, "N");
		
		/*
		$arEventFields = array(
			"EMAIL_TO"	=> $arItem["EMAIL"],
			"MESSAGE"	=> $message,
			"SUBJECT"	=> ($arItem["SITE_ID"] == "s1" ? "tempusshop.ru" : "tempus.by") . ". Товар {$arItem["NAME"]} поступил на склад и доступен для заказа",
		);
					
		CEvent::Send("IM_NEW_MESSAGE", $arItem["SITE_ID"], $arEventFields, "N");
		*/
		
		$el = new CIBlockElement;
		$el->Update($arItem["ID"], array("ACTIVE" => "N"));
		
		CLog::add2log(array("event" => "R", "text" => $log_text));
	}
}
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
$workers->updateStatus("N");
?>
