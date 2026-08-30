<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("local_cron_offline_pricelist_ru_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(3600);
GLOBAL $DB;

function getActualPrices( array $models )
{
  $array = [
    '_token' => md5( "price_tag_" . date('Y-m-d') ),
    'data' => $models,
  ];
  $header = [
    'Content-Type: application/json',
  ];

  $data = json_encode( $array );

  $ch = curl_init( "https://tempus.ru/local/ajax/priceTags/" );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
  curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
  $res = curl_exec($ch);

  return json_decode($res, true);
}

$arPrice = array();
$strSql = "SELECT * FROM offline_price_ru";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$flg = false;
while ($row = $results->Fetch()){
  $arSQL[$row['article']] = ['old_price' => $row['old_price'], 'price' => $row['price']];
}

$arSelect = Array("ID","IBLOCK_ID","PROPERTY_123","PROPERTY_AVAILABILITY_RU","PROPERTY_MINIMUM_PRICE");
$arFilter = Array(
  "IBLOCK_ID" => CProSet::IB_CATALOG,
  "PROPERTY_AVAILABILITY_RU" => 2126,
  // "ACTIVE" => "Y",
);

$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
while ($el = $result->GetNext()){
	$items[$el['PROPERTY_123_VALUE']] = $el;
}
$pricesRaw = getActualPrices( array_keys($items) );
$pricesSite = [];
foreach ( $pricesRaw as $item ){
  $pricesSite[ $item['model'] ] = $item['min_price'];
}

foreach ($items as $key => $item) {
  if (isset($arSQL[$item['PROPERTY_123_VALUE']])) {
    $price = $pricesSite[ $item['PROPERTY_123_VALUE'] ] ?? $item['PROPERTY_MINIMUM_PRICE_VALUE'];
    $in = array(
      'old_price' => "'".$arSQL[$item['PROPERTY_123_VALUE']]['price']."'",
  		'price' => intval( $price ),
  		'active' => "'Y'",
    );
    $DB->Update("offline_price_ru", $in, "WHERE article ='".$item['PROPERTY_123_VALUE']."'", $err_mess.__LINE__);
  } else {
    $price = $pricesSite[ $item['PROPERTY_123_VALUE'] ] ?? $item['PROPERTY_MINIMUM_PRICE_VALUE'];
    $in = array(
      "article" => "'".$item['PROPERTY_123_VALUE']."'",
      "old_price" => 0,
      "price" => intval( $price ),
      "active" => "'Y'"
    );
    $DB->Insert("offline_price_ru", $in, $err_mess.__LINE__);
  }
}

foreach ($arSQL as $key => $item) {
  if (!isset($items[$key])) {
	$in = array(
		'active' => "'N'",
      );
      $DB->Update("offline_price_ru", $in, "WHERE article ='".$key."'", $err_mess.__LINE__);
  }
}
