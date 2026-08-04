#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
// 
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);
/*
if (function_exists('ini_set')) ini_set('max_execution_time','1000000');
if (function_exists('ini_set')) ini_set('default_socket_timeout','120');
if (function_exists('ini_set')) ini_set('allow_url_fopen','On');
if (function_exists('ini_set')) ini_set('memory_limit','1024M');
		
if (function_exists('ini_set')) ini_set('apc.user_entries_hint', 32768);
if (function_exists('ini_set')) ini_set('apc.num_files_hint', 32768);
if (function_exists('ini_set')) ini_set('apc.rfc1867_freq', '10k');
*/
CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("catalog");
if (!\Bitrix\Main\Loader::includeModule('mlife.smsservices') || !\Bitrix\Main\Loader::includeModule('rarus.sms4b')) return;

$arResult = array();
$arFilter = Array(
	"IBLOCK_ID"	=> CProSet::IB_SUBSCRIBE_PRODUCT,
	"ACTIVE"	=> "Y",
	"<PROPERTY_CNT_ATTEMPTS" => 3,
);
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "PROPERTY_PHONE", "PROPERTY_SITE_ID", "PROPERTY_PRODUCT_ID", "PROPERTY_CNT_ATTEMPTS"));
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

		$rsEl = CIBlockElement::GetList(array(), $arFilterEl, false, false, array("ID", "NAME"));
		if($arFields = $rsEl->GetNext()){
			$arResult["ITEMS"][] = array(
				"ID" => $ar["ID"],
				"PRODUCT_ID" => $arFields["ID"],
				"NAME" => $arFields["NAME"],
				"SITE_ID" => $ar["PROPERTY_SITE_ID_VALUE"],
				"PHONE" => $ar["PROPERTY_PHONE_VALUE"],
				"CNT_ATTEMPTS" => $ar["PROPERTY_CNT_ATTEMPTS_VALUE"],
			);

		}
	}
}
//prent($arResult["ITEMS"],0,1);
if(count($arResult["ITEMS"]) > 0){
	foreach($arResult["ITEMS"] as $key => $arItem){
		$log_text = "Неизвестная ошибка. Товар - " . $arItem["NAME"];
		$flg_send = false;
		if($arItem["SITE_ID"] == "s1"){
			$message = "Товар {$arItem["NAME"]} поступил на склад и доступен для заказа. Заказ можно оформить по телефону +7(800)500-65-42 или на сайте tempusshop.ru";
			$SMS4B = new Csms4b();
			if(!$SMS4B->SendSMS($message, $arItem["PHONE"])){
				$log_text = "Подписка на товар - {$arItem["NAME"]}. Ошибка отправки смс. tempusshop.ru - " . $arItem["PHONE"];
			}else{
				$log_text = "Подписка на товар - {$arItem["NAME"]}. Сообщение успешно отправлено - " . $arItem["PHONE"];
				$flg_send = true;
			}
		}elseif($arItem["SITE_ID"] == "s2"){
			$message = "Товар {$arItem["NAME"]} поступил на склад и доступен для заказа. Заказ можно оформить по телефону +375293449966 или на сайте www.tempus.by";
			$transport = new \Mlife\Smsservices\Sender();
			$arSend = $transport->sendSms($arItem["PHONE"], $message);
			if(!is_object($arSend) || $arSend->error) {
				$log_text = "Подписка на товар - {$arItem["NAME"]}. Ошибка отправки смс. tempus.by " . $arItem["PHONE"] . " " . $arSend->error . ", код ошибки: " . $arSend->error_code;
			}else{
				$log_text = "Подписка на товар - {$arItem["NAME"]}. Сообщение успешно отправлено - " . $arItem["PHONE"];
				$flg_send = true;
			}
		}
		$cnt = intval($arItem["CNT_ATTEMPTS"]) + 1;
		CIBlockElement::SetPropertyValuesEx($arItem["ID"], false, array("CNT_ATTEMPTS" => $cnt));
		if($flg_send === true || $cnt > 2){
			$el = new CIBlockElement;
			$el->Update($arItem["ID"], array("ACTIVE" => "N"));
		}
		
		
		
		CLog::add2log(array("event" => "SMS", "text" => $log_text));
	}
}
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>