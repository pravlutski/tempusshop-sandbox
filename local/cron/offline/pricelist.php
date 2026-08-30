<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("local_cron_offline_pricelist_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(3600);
GLOBAL $DB;

$arPrice = array();
$strSql = "SELECT * FROM offline_price";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$flg = false;
while ($row = $results->Fetch()){
  $arSQL[$row['article']] = ['old_price' => $row['old_price'], 'price' => $row['price']];
}

$arSelect = Array("ID","IBLOCK_ID","PROPERTY_123","PROPERTY_AVAILABILITY_BY","CATALOG_PRICE_2");
$arFilter = Array(
  "IBLOCK_ID" => CProSet::IB_CATALOG,
  "PROPERTY_AVAILABILITY_BY" => 492,
  "ACTIVE" => "Y",
);

$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
while ($el = $result->GetNext()){
	$items[$el['PROPERTY_123_VALUE']] = $el;
}

foreach ($items as $key => $item) {
  if (isset($arSQL[$item['PROPERTY_123_VALUE']])) {
 $in = array(
        'old_price' => "'".$arSQL[$item['PROPERTY_123_VALUE']]['price']."'",
		'price' => "'".$item['CATALOG_PRICE_2']."'",
		'active' => "'Y'",
      );
      $DB->Update("offline_price", $in, "WHERE article ='".$item['PROPERTY_123_VALUE']."'", $err_mess.__LINE__);
  } else {
    $in = array(
      "article" => "'".$item['PROPERTY_123_VALUE']."'",
      "old_price" => intval($item['CATALOG_PRICE_2']),
      "price" => intval($item['CATALOG_PRICE_2']),
      "active" => "'Y'"
    );
    $DB->Insert("offline_price", $in, $err_mess.__LINE__);
  }
}

foreach ($arSQL as $key => $item) {
  if (!isset($items[$key])) {
	$in = array(
		'active' => "'N'",
      );
      $DB->Update("offline_price", $in, "WHERE article ='".$key."'", $err_mess.__LINE__);
  }
}
$workers->updateStatus("N");
