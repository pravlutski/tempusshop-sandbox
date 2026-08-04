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
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/collect.txt", print_r('START', true) . "\r\n");
CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');

global $DB;
$strSql = "SELECT * FROM illiquid_wb";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$groups = [];
$cardsBIds = [];
while ($row = $resultDB->Fetch()){
  $cardsBIds[] = $row['bitrixId'];
  $groups[$row['completeId']][] = $row['nmid'];
}

$colArray = array();
$arFilterN["ID"] = array_merge(CMaxyssWb::getItemsWB(), $cardsBIds);
$arFilterN["IBLOCK_ID"] = 16;
$arFilterN["!PROPERTY_PROP_MAXYSS_NMID_CREATED_WB"] = false;
$arSelectN = Array("ID", "NAME", "PROPERTY_PROP_MAXYSS_NMID_CREATED_WB", "PROPERTY_2740");
$rs = CIBlockElement::GetList(array(), $arFilterN, false, false, $arSelectN);
while($ob = $rs->GetNextElement())
{
  $arFields = $ob->GetFields();

  foreach ($arFields['PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_DESCRIPTION'] as $key => $value) {
    if ($value == 'WR') {
      $nmid = $arFields['PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_VALUE'][$key];
    }
  }
  if ( $match = recursiveSearch($nmid, $groups) ){
    // if ( !array_search($colValue[$match]) ){
    // }
    $nmid = intval($nmid);
    $colValue = $match;
    $colArray[$colValue][] = $nmid;
    // CAddinMaxyssWB::CreateCollection([$nmid]);
    // file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/illiquid.txt', print_r($colArray[$colValue], true) . PHP_EOL, FILE_APPEND);
  }else{
    $colValue = $arFields['PROPERTY_2740_VALUE'];
    if (!isset($colArray[$colValue])) {
      $colArray[$colValue] = array();
    }
    $colArray[$colValue][] = intval(array_shift($arFields['PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_VALUE']));
  }
}

// echo '<pre>';
// var_dump($colArray);
// echo '</pre>';
// die;

foreach ($colArray as $key => $col) {
	if (count($col) > 1) {
		// $result[$key] = CAddinMaxyssWB::CreateCollection($col);
    sleep(1);
	}
}



function recursiveSearch($needle, $haystack){
  foreach ($haystack as $key => $value) {
    if ( in_array($needle, $value) ){
      return $key;
    }
  }
  return false;
}
