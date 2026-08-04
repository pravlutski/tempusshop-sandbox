<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;
require_once '/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';


$options = $_GET;
$day = $_GET['date'];


$tmp = file_get_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/ozon/ajax/stat/IP/selected_warehouses.txt");

$active_warehouses = [];
if (!empty($tmp)) {
  $active_warehouses = explode(', ' ,$tmp);
}


$strSql = "SELECT model,warehouse_name FROM ozon_stock_fbo_stat WHERE date = '{$day}'";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$warehouses = [];
while ( $row = $resultDB->Fetch() ){
  if (!empty($row['warehouse_name']) && $row['warehouse_name'] != 'null') {
    $tmpWh = json_decode($row['warehouse_name'],true);
    $warehouses[$row['model']] = 0;

    foreach ($tmpWh as $key => $value) {
      $warehousesTemplate[$key] = $key;
      if (in_array($key,$active_warehouses)) {
        $warehouses[$row['model']] = $warehouses[$row['model']] + $value;
      }
    }
  }
}


$flag = false;
$strSql = "SELECT 1 FROM ozon_stock_fbo_stat WHERE date = '{$day}'";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
if ( $resultDB->SelectedRowsCount() == 0 ){
  $flag = true;
  $day = date( 'Y-m-d',strtotime($day . ' - 1 day') );
}

$dayBefore = date('Y-m-d',strtotime($day . ' - 1 day' ));
$strSql = "SELECT DISTINCT date FROM ozon_stock_fbo_stat";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);

while ( $row = $resultDB->Fetch() ){
  $dates[$row['date']] = 1;
}
if ( empty($dates[$day]) ){
  die('<span>Нет данных за сегодня</span>');
}
$strSql = "SELECT * FROM ozon_stock_fbo_stat WHERE ";
foreach ($options as $key => $option) {
  switch ($key) {
    case 'date':
     if ($option != ''){
       $strSql .= "{$key} = '{$option}'";
     }else{
       die('Дата - обязательный параметр');
     }
    break;
  }
}
$strSql .= " AND model NOT IN (SELECT model FROM ozon_stock_fbo_stat WHERE date = '{$dayBefore}')";

$strSql = "SELECT model, sku, stock, price
FROM ozon_stock_fbo_stat
WHERE date = '{$day}'
    AND model NOT IN (SELECT model FROM ozon_stock_fbo_stat WHERE date = '{$dayBefore}')
UNION
SELECT td.model, td.sku, td.stock - yd.stock as stock, td.price
FROM ozon_stock_fbo_stat AS td
JOIN (SELECT model, sku,stock, price FROM ozon_stock_fbo_stat WHERE date = '{$dayBefore}') as yd
ON td.model = yd.model
WHERE td.date = '{$day}'";
// var_dump($strSql);
// die;
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$goodsAdd = [];
$sumAdd = 0;
$sumAddStock = 0;
while ( $row = $resultDB->Fetch() ){
  if ( $row['stock'] <= 0 ) continue;
  $goodsAdd[] = $row;
  $sumAdd += $row['price'] * $row['stock'];
  $sumAddStock += $row['stock'];
}
// $strSql = "SELECT * FROM ozon_stock_fbo_stat WHERE date = '{$dayBefore}' AND sku NOT IN (SELECT sku FROM auto_adv_wb_stat WHERE date = '{$day}')";
$strSql = "SELECT model, sku, stock, price
FROM ozon_stock_fbo_stat
WHERE date = '{$dayBefore}'
    AND model NOT IN (SELECT model FROM ozon_stock_fbo_stat WHERE date = '{$day}')
UNION
SELECT td.model, td.sku, yd.stock - td.stock as stock, td.price
FROM ozon_stock_fbo_stat AS td
JOIN (SELECT model, sku,stock, price FROM ozon_stock_fbo_stat WHERE date = '{$dayBefore}') as yd
ON td.model = yd.model
WHERE td.date = '{$day}'";
// var_dump($strSql);
// die;
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$goodsSold = [];
$sumSold = 0;
$sumSoldStock = 0;
while( $row = $resultDB->Fetch() ){
  if ( $row['stock'] <= 0 ) continue;
  $goodsSold[] = $row;
  $sumSold += $row['price'] * $row['stock'];
  $sumSoldStock += $row['stock'];
}

CModule::includeModule('panel.manager');
$dbPanel = new DBPanel;

$result = $dbPanel->query("SELECT * FROM ozon_top_models");
$rows = $dbPanel->fetchAll($result);
foreach ($rows as $row) {
  $tops[] = $row['model'];
}

$day = isset($day) ? $day : date('Y-m-d');
$strSql = "SELECT * FROM ozon_stock_fbo_stat WHERE date = '{$day}'";
$res = $DB->Query($strSql, false, $err_mess.__LINE__);
$stock = [];
while( $row = $res->Fetch() ){
  $stock[$row['model']] = ['stock' => $row['stock'], 'wh' => $row['warehouse_name']];
}

$topChunks = [10,50,100,200];
$topStat = [
  'top_10' => 0,
  'top_50' => 0,
  'top_100' => 0,
  'top_200' => 0,
  // 'top_309' => 0,
];

$topStatMpscow = [
  'top_10' => 0,
  'top_50' => 0,
  'top_100' => 0,
  'top_200' => 0,
];

$topCheck = [
  'top_10' => [],
  'top_50' => [],
  'top_100' => [],
  'top_200' => [],
];
$arDebug = [];
foreach ( $topChunks as $lim ){
  for ( $i = 0; $i < $lim; $i++ ){
    if ( isset($tops[$i]) && isset($stock[$tops[$i]]) && $stock[$tops[$i]]['stock'] > 0 ){
      $topStat['top_' . $lim] += 1;
      $topCheck['top_' . $lim][] = $tops[$i];
      if (isset($warehouses[$tops[$i]]) && intval($warehouses[$tops[$i]] != 0)) {
        $topStatMpscow['top_' . $lim] += 1;
        $arDebug['MSC_top_' . $lim][$tops[$i]] = intval($warehouses[$tops[$i]]);
      }
    }
  }
}

// print_r($arDebug);
$topName = [
  'top_10' => 'Топ 10',
  'top_50' => 'Топ 50',
  'top_100' => 'Топ 100',
  'top_200' => 'Топ 200',
  // 'top_309' => 'Топ 309',
];


$filepath = $_SERVER["DOCUMENT_ROOT"] . "/upload/top_msc_ozon_ip.xlsx";
$objPHPExcel = new PHPExcel();
$objPHPExcel->setActiveSheetIndex(0);
$sheet = $objPHPExcel->getActiveSheet();
$title = 'Топ по МСК складам';
//print_r($title);
//die();
$sheet->setTitle($title);
$row = 2;
$column = 0;
foreach($arDebug as $topName => $arItems){

  $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column, $row, $topName);
  $cell = $objPHPExcel->getActiveSheet()->getCellByColumnAndRow($column, $row);
  $cell->getStyle()->getFont()->setBold(true);
  $row++;

  foreach($arItems as $product => $q){

        $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column, $row, $product);
        $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column+1, $row, $q);
        $row++;
  }

  $row = $row+3;

}
$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$objWriter->save($filepath);
/*
$arCsv[0] = iconv("UTF-8", "WINDOWS-1251", "Наименование");
$arCsv[1] = iconv("UTF-8", "WINDOWS-1251", "Закупочная цена");
$str_csv = implode(";", $arCsv) . "\r\n";
file_put_contents($filepath , $str_csv, FILE_APPEND);


foreach($arGroup as $supp_id => $arItems){
$sup_name = iconv("UTF-8", "WINDOWS-1251", $arResult["SUPPLIER_NAME"][$supp_id]);
$sup_name = '"' . $sup_name . '"';
file_put_contents($filepath , $sup_name . "\r\n", FILE_APPEND);
foreach($arItems as $arItem){
  $arCsv[0] = ($arBitrix[$arItem["product_id"]] ? $arBitrix[$arItem["product_id"]] : $arItem["model"]);
  $arCsv[0] = iconv("UTF-8", "WINDOWS-1251", $arCsv[0]);
  $arCsv[1] = str_replace(".", ",", $arItem["price"]);
  $str_csv = '"'.implode('";"', $arCsv) . '"' . "\r\n";
  file_put_contents($filepath , $str_csv, FILE_APPEND);
}
file_put_contents($filepath , "\r\n\r\n\r\n", FILE_APPEND);
}
*/

file_force_download($filepath);
