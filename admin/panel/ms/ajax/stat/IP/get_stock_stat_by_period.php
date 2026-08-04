<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

CModule::IncludeModule('panel.manager');

$minDate = $_POST['min_date_stat'];
$maxDate = $_POST['max_date_stat'];

$strSql = "SELECT avg(stock) as stock, avg(price) as price FROM (SELECT date, sum(stock) as stock, sum(stock * price) as price FROM ozon_stock_fbo_stat";

if( empty($minDate) && empty($maxDate) ){
  echo json_encode(['error' => 'Не задан период']);
}
if ( !empty($minDate) && empty($maxDate) ){
  $strSql .= " WHERE date >= '{$minDate}'";
}
if ( !empty($maxDate) && empty($minDate) ){
  $strSql .= " WHERE date <= '{$maxDate}'";
}
if ( !empty($maxDate) && !empty($minDate) ){
  $strSql .= " WHERE date >= '{$minDate}' AND date <='{$maxDate}'";
}
$strSql .= "GROUP BY date) AS ss";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $avgStock = intval($row['stock']);
  $avgPrice = intval($row['price']);
}

$strSql = "SELECT * FROM ozon_stock_fbo_stat";
if ( !empty($minDate) && empty($maxDate) ){
  $strSql .= " WHERE date >= '{$minDate}'";
}
if ( !empty($maxDate) && empty($minDate) ){
  $strSql .= " WHERE date <= '{$maxDate}'";
}
if ( !empty($maxDate) && !empty($minDate) ){
  $strSql .= " WHERE date >= '{$minDate}' AND date <='{$maxDate}'";
}
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$statsFboData = [];
$statsStockData = [];
$statsModelData = [];
while ( $row = $resultDB->Fetch() ){
  if ( isset( $statsFboData[$row['date']] ) ){
    $statsFboData[$row['date']] += $row['stock'] * $row['price'];
    $statsStockData[$row['date']] += $row['stock'];
		$statsModelData[$row['date']] += 1;
  }else{
    $statsFboData[$row['date']] = $row['stock'] * $row['price'];
    $statsStockData[$row['date']] = $row['stock'];
		$statsModelData[$row['date']] = 1;
  }
}

echo json_encode([
  'stock' => number_format($avgStock, 0, '', ' '),
  'price' => number_format($avgPrice, 0, '', ' '),
  'statsStock' => $statsStockData,
  'statsPrice' => $statsFboData,
	'statsModel' => $statsModelData,
  'error' => ''
]);
 ?>
