<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

$path = "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/avito_bad.txt";

$json = file_get_contents( $path );
$ids = json_decode( $json, true );

function getBrands()
{
  $arFilter = ['IBLOCK_ID' => 11];
  $arSelect = [
    "ID", "IBLOCK_ID", "NAME"
  ];
  $res = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );
  $result = [];

  while ( $row = $res->GetNext() ){
    $result[ $row['ID'] ] = $row['NAME'];
  }

  return $result;
}

$arFilter = [
  'IBLOCK_ID' => 16,
  // 'ID' => 1002,
  'ID' => $ids
];
$arSelect = [
  "ID", "IBLOCK_ID", "PROPERTY_3081", "PROPERTY_TYPE", "PROPERTY_COLOR", "PROPERTY_MECHANISM", "PROPERTY_MATERIAL", "PROPERTY_BRAND"
];
$res = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );

$brands = getBrands();
$items = [];
while ( $row = $res->GetNext() )
{
  var_dump($row);
  $items[ $row["ID"] ] = [
    'Gender' => reset($row['PROPERTY_TYPE_VALUE']),
    'Color' => reset($row['PROPERTY_COLOR_VALUE']),
    'Brand' => $brands[ $row['PROPERTY_BRAND_VALUE'] ],
    'StrapType' => reset($row['PROPERTY_MATERIAL_VALUE']),
    'Mechanism' => reset($row['PROPERTY_MECHANISM_VALUE'])
  ];

  var_dump($items);
  die;
}

 ?>
