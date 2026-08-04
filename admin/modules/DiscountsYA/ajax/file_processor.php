<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");

require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $filename = $_FILES['file']['tmp_name'];
  }else{
    die('Ошибка загрузки файла');
  }

if ( empty($_POST) ){
  die('Не заполнены поля');
}
$settingsPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsYA/settings/settings.json';
$arSettings = json_decode( file_get_contents($settingsPath), true );

$arFields = $_POST;
// var_dump($_POST);
// die('Я умер');
$arSale = parseXlsx($filename,$arFields, $arSettings);

function getItems()
{
  $arFilter = [
    'IBLOCK_ID' => 16,
    'ID' => $arIDs
  ];
  $arSelect = ['IBLOCK_ID','ID','PROPERTY_CML2_ARTICLE'];
  $result = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
  $data = [];
  while ( $row = $result->GetNext() ){
    $data[$row['ID']] = $row['PROPERTY_CML2_ARTICLE_VALUE'];
  }
  return $data;
}

function getCost($bid){
  global $DB;
  $strSql = "SELECT cp.price AS price, cp.bitrix_id AS bitrix_id, cpc.price_ya AS price_ya, cp.count AS count, cp.id AS id
  FROM ci_price cp
  JOIN ci_price_catalog AS cpc
  ON cp.bitrix_id = cpc.product_id
  WHERE active_ya = 'Y' AND supplier_id != 103 AND bitrix_id = {$bid}
  ORDER BY price ASC";
  $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
  $arCost = [];
  while ( $row = $resultDB->Fetch() ){
    $arCost[$row['id']] = [
      'cost' => $row['price'],
      'price_ya' => $row['price_ya'],
      'count' => $row['count'],
      'bitrix_id' => $row['bitrix_id']
    ];
  }

  $strSql = "SELECT ARTICLE, RESERVED FROM ci_reserved WHERE PRODUCT_ID = {$bid}";
  $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
  $reserved = [];
  while ( $row = $resultDB->Fetch() ){
    $reserved[$row['PRODUCT_ID']] = $row['RESERVED'];
  }
  $curReserved = null;
  foreach ($arCost as $id => $propose) {
    if ( empty($curReserved) ){
      if ( $propose['count'] - $reserved[ $propose['bitrix_id'] ] > 0 ){
        return ['cost' => $propose['cost'], 'price_ya' => $propose['price_ya']];
      }else{
        $curReserved = abs($propose['count'] - $reserved[ $propose['bitrix_id'] ]);
        continue;
      }
    }else{
      if ( $propose['count'] - $curReserved > 0 ){
        return ['cost' => $propose['cost'], 'price_ya' => $propose['price_ya']];
      }else{
        $curReserved = abs( $propose['count'] - $curReserved );
        continue;
    }
  }
}
  return false;
}

function parseXlsx($filename, $arFields, $arSettings){
  // var_dump($arFields);
  $xls = PHPExcel_IOFactory::load($filename);
  $xls->setActiveSheetIndex(1);
  $sheet = $xls->getActiveSheet();
  $arSale = [];
  $exportCsv[] = [
    'SKU',
    'Артикул',
    'Маржинальность, ₽',
    'Маржинальность, %',
    'Себестоимость, ₽',
    'Цена продажи, ₽',
    'Макс. цена для участия в акции, ₽'
  ];
  $arItems = getItems();
    foreach ($sheet->toArray() as $key => $row) {
      if ( !preg_match('/[0-9]/', $row[$arSettings['sku_col']]) ) continue;
      $arCost[ $row[ $arSettings['sku_col'] ] ] = getCost( $row[ $arSettings['sku_col'] ] );
      if ( empty($arCost) ){
        $arLog['no_price'][] = $row[$arSettings['sku_col']];
        continue;
      }
      if ( empty($arCost[intval($row[$arSettings['sku_col']])]) ){
        $arLog['no_vendorCode'][] = $row[$arSettings['sku_col']];
        continue;
      }
      $b = round($arCost[intval($row[$arSettings['sku_col']])]['cost']);
      $a = intval($row[$arSettings['price_col']]);
      $c = $arFields['comission'] / 100;
      $calculatedMargin = ($a * (1 - $c) - $b) / $b * 100;
      if ( $calculatedMargin >= $arFields['margin'] ){
        $arSale[] = [
          'sku' => $row[$arSettings['sku_col']],
          'model' => $arItems[$row[$arSettings['sku_col']]],
          'flatMargin' => number_format($a * (1 - $c) - $b, 2 ),
          'calcMargin' => number_format($calculatedMargin, 2),
          'cost' => round($arCost[intval($row[$arSettings['sku_col']])]['cost']),
          'price_ya' => $arCost[intval($row[$arSettings['sku_col']])]['price_ya'],
          'salePrice' => $row[$arSettings['price_col']],
        ];
        $exportCsv[] = [
          'sku' => $row[$arSettings['sku_col']],
          'model' => $arItems[$row[$arSettings['sku_col']]],
          'flatMargin' => number_format($a * (1 - $c) - $b, 2 ),
          'calcMargin' => number_format($calculatedMargin, 2),
          'cost' => round($arCost[intval($row[$arSettings['sku_col']])]['cost']),
          'price_ya' => $arCost[intval($row[$arSettings['sku_col']])]['price_ya'],
          'salePrice' => $row[$arSettings['price_col']],
        ];
      }
    }
    $arLog['good'] = $arSale;
    $logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsYA/logs/log.txt';
    file_put_contents( $logPath, json_encode($arLog) );
    $fname = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsYA/temp/export.csv';
    $stream = fopen($fname, 'w');
    foreach ( $exportCsv as $string ){
      fputcsv($stream, $string);
    }
    fclose($stream);
    return $arSale;

}
?>
<div class="counter">
  <span class="counter"><b>Проходит по условиям товаров: </b><?php echo count($arSale);?></span>
  <a class="export-link" href="/admin/modules/DiscountsYA/temp/export.csv" download>Экспорт в CSV</a>
</div>
<table class="result-table">
  <thead>
    <tr>
      <th>SKU</th>
      <th>Артикул</th>
      <th>Маржинальность, ₽</th>
      <th>Маржинальность, %</th>
      <th>Себестоимость, ₽</th>
      <th>Цена продажи, ₽</th>
      <th>Макс. цена для участия в акции, ₽</th>
    </tr>
  </thead>
  <tbody>
<?php
foreach ($arSale as $key => $position):
 ?>
     <tr>
       <td><?php echo $position['sku'];?></td>
       <td><?php echo $position['model'];?></td>
       <td><?php echo $position['flatMargin'];?></td>
       <td><?php echo $position['calcMargin'];?></td>
       <td><?php echo $position['cost'];?></td>
       <td><?php echo $position['price_ya'];?></td>
       <td><?php echo $position['salePrice'];?></td>
     </tr>
<?php endforeach;?>
   </tbody>
 </table>
