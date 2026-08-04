<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$dbMain = \Bitrix\Main\Application::getConnection();
$dbPanel = new DBPanel;

function getBitrixData()
{
  $arFilter = [
    'IBLOCK_ID' => 16,
    '!PROPERTY_WBARTICLE2' => false,
  ];
  $arSelect = ['ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_CML2_ARTICLE', 'PROPERTY_WBARTICLE2'];
  $result = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
  $items = [];

  while ( $row = $result->GetNext() ){
    $items[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ] = [
      'offer_id' => $row['PROPERTY_WBARTICLE2_VALUE'],
      'name' => $row['NAME']
    ];
  }

  return $items;
}

function getData()
{
  $db = \Bitrix\Main\Application::getConnection();
  $strSql = "SELECT ARTICLE, RESERVED FROM ci_reserved";
  $result = $db->Query( $strSql );
  $reserved = [];

  while ( $row = $result->Fetch() ){
    $reserved[ $row['ARTICLE'] ] = $row['RESERVED'];
  }

  $strSql = "SELECT id, name FROM ci_brands";
  $result = $db->Query( $strSql );
  $brands = [];

  while ( $row = $result->Fetch() ){
    $brands[ $row['id'] ] = $row['name'];
  }

  $strSql = "SELECT model, price, count, brand_id FROM ci_price WHERE active_wb = 'Y' AND model NOT IN ('ФУТЛЯР', 'КОРОБКА', 'КОРО') ORDER BY price ASC";

  $result = $db->Query( $strSql );
  $items = [];

  while ( $row = $result->Fetch() ) {
    $items[ $row['model'] ][] = [
        'brand_id' => $row['brand_id'],
        'count' => $row['count'],
        'price' => $row['price'],
    ];
  }

  $bitrixItems = getBitrixData();
  $result = [];
  foreach ( $items as $model => $arItem ){
    $r = $reserved[$model] ?? 0;
    foreach ( $arItem as $itemPrice ){
      $restRes = $itemPrice['count'] - $r;
      if ( $restRes <= 0 ) continue;

      $result[] = [
        'Наименование' => $bitrixItems[$model]['name'] ?? '',
        'Артикул' => $model,
        'Артикул WB' => $bitrixItems[$model]['offer_id'] ?? '',
        'Себестоимость' => $itemPrice['price']
      ];
      break;
    }
  }
  return $result;
}

function arrayToCsv($data, $filename = "export.csv")
{
    // Открываем файл для записи
    $file = fopen($filename, 'w');

    // Добавляем BOM для корректного отображения кириллицы в Excel
    // fputs($file, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    // Записываем заголовки (если массив ассоциативный)
    if (!empty($data)) {
        $firstRow = $data[0];
        if (is_array($firstRow)) {
            fputcsv($file, array_keys($firstRow));
        }

        // Записываем данные
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
    }

    fclose($file);
}

$items = getData();
arrayToCsv( $items, '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/export/cost_WB.csv' );
 ?>
