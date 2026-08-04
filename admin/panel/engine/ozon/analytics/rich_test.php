<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$json = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/configs/tissot_rich.json');
$template = json_decode( $json, true );

$sections = getSections();
$brands = getBrands();

$arFilter = [
  'IBLOCK_ID' => 16,
  'ID' => 181463,
  'PROPERTY_BRAND' => 43508
];

$arSelect = ['IBLOCK_ID', 'ID', 'PROPERTY_CML2_ARTICLE', 'PROPERTY_BRAND', 'PROPERTY_DESC_RICH_OZON', 'DETAIL_PICTURE', 'IBLOCK_SECTION_ID'];

$row = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect )->GetNextElement()->GetFields();

$card = [
  'model' => $row['PROPERTY_CML2_ARTICLE_VALUE'],
  'description' => $row['PROPERTY_DESC_RICH_OZON_VALUE']['TEXT'],
  'picture' => 'https://tempusshop.ru'.CFile::GetPath($row['DETAIL_PICTURE']),
  'section' => $sections[ $row['IBLOCK_SECTION_ID'] ],
  'brand' => $brands[ $row['PROPERTY_BRAND_VALUE'] ],
];
// Изображение (Деталка)
$template['content'][2]['blocks'][0]['img']['src'] = $card['picture'];
$template['content'][2]['blocks'][0]['img']['srcMobile'] = $card['picture'];
// Наименование
$template['content'][2]['blocks'][0]['title']['items'][0]['content'] = "{$card['brand']} {$card['section']}";
$template['content'][2]['blocks'][0]['text']['items'][0]['content'] = $card['model'];
// Описание
$template['content'][3]['text']['items'][0]['content'] = $card['description'];

echo json_encode( $template );

function getBrands()
{
  $arFilter = Array(
    "IBLOCK_ID" => CProSet::IB_BRANDS,
  );
  $result = CIBlockElement::GetList( Array(), $arFilter, false, false, array("ID", "NAME") );
  while ( $arFields = $result->GetNext() ){
    $brands[ $arFields["ID"] ] = $arFields["NAME"];
  }

  return $brands;
}

function getSections()
{
  $res = CIBlockSection::GetList(
    Array("SORT"=>"ASC"),
    Array("IBLOCK_ID" => 16),
    false,
    Array('ID','NAME'),
    false
  );

  while ( $item = $res->GetNext() ){
    $sections[ $item['ID'] ] = $item['NAME'];
  }

  return $sections;
}
 ?>
