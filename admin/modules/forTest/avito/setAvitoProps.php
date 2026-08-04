<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

$path = "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/avito/avito_bad.txt";

$json = file_get_contents( $path );
$ids = json_decode( $json, true );

function getDictionaries()
{
  $list = ['brand', 'color', 'sex', 'mechanism', 'strap'];
  $path = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/avito/dictionary/%s.json';
  $result = [];

  foreach ( $list as $name ){
    $json = file_get_contents( sprintf($path, $name) );
    $result[$name] = json_decode( $json, true );
  }

  return $result;
}

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
$dictionary = getDictionaries();
$items = [];

$required = [];

while ( $row = $res->GetNext() )
{
  $type = reset($row['PROPERTY_TYPE_VALUE']);
  $color = reset($row['PROPERTY_COLOR_VALUE']);
  $brand = $brands[ $row['PROPERTY_BRAND_VALUE'] ];
  $strap = reset($row['PROPERTY_MATERIAL_VALUE']);
  $mechanism = reset($row['PROPERTY_MECHANISM_VALUE']);

  $props = [
    'Gender' => $dictionary['sex'][ $type ],
    'Color' => $dictionary['color'][ $color ],
    'Brand' => $dictionary['brand'][ $brand ],
    'StrapType' => $dictionary['strap'][ $strap ],
    'Mechanism' => $dictionary['mechanism'][ $mechanism ],
  ];
  // $r = CIBlockElement::SetPropertyValueCode(
  //   $row['ID'],
  //   3081,
  //   $props
  // );
  // CIBlockElement::SetPropertyValuesEx(
  //   $row['ID'],
  //   16,
  //   array(3081 => $props)
  // );
  $id = $row['ID'];
  $DB->Update("b_iblock_element_prop_s16", array("PROPERTY_3081" => "'".serialize($props)."'"), "WHERE IBLOCK_ELEMENT_ID = '$id'", $err_mess.__LINE__);
  var_dump( $row['ID'] );
  // var_dump( $r );
  // die;
}



 ?>
