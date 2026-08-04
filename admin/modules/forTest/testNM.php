<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

function getSectionDictionaryL()
{
  $res = CIBlockSection::GetList(
      ['LEFT_MARGIN' => 'ASC'],
      ['IBLOCK_ID' => 16, 'ACTIVE' => 'Y'],
      false,
      ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'DEPTH_LEVEL']
  );

  $sections = [];
  $rootMap = []; // Храним корень для каждого раздела
  $parentStack = []; // Стек родителей для определения корня

  while ($section = $res->Fetch()) {
      $sectionId = $section['ID'];
      $parentId = $section['IBLOCK_SECTION_ID'];
      $depth = $section['DEPTH_LEVEL'];

      $sections[$sectionId] = $section;

      // Определяем корневой раздел для текущего
      if ($depth == 1) {
          $rootMap[$sectionId] = $sectionId; // Сам себе корень
          $parentStack[$depth] = $sectionId;
      } else {
          // Корень такой же, как у родителя
          $rootMap[$sectionId] = $rootMap[$parentId];
          $parentStack[$depth] = $sectionId;
      }
  }

  // Находим самые глубокие разделы (не имеющие детей)
  $hasChildren = array_fill_keys(array_keys($sections), false);
  foreach ($sections as $sectionId => $section) {
      $parentId = $section['IBLOCK_SECTION_ID'];
      if ($parentId > 0) {
          $hasChildren[$parentId] = true;
      }
  }

  // Формируем итоговый словарь
  $deepToRoot = [];
  foreach ($sections as $sectionId => $section) {
      if (!$hasChildren[$sectionId]) {
          $deepToRoot[$sectionId] = $rootMap[$sectionId];
      }
  }

  return $deepToRoot;
}

function getSectionDictionary():array
{
  $res = CIBlockSection::GetList(
      ['DEPTH_LEVEL' => 'ASC'],
      ['IBLOCK_ID' => 16, 'ACTIVE' => 'Y'],
      false,
      ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'DEPTH_LEVEL']
  );

  $roots = [];
  $brands = [];
  $collections = [];

  $result = [];
  $brands = [];
  while( $row = $res->getNext() )
  {
    if ( $row['DEPTH_LEVEL'] == 1 ) continue;
    if ( $row['DEPTH_LEVEL'] == 2 ){
      $brands[ $row['ID'] ] = $row['IBLOCK_SECTION_ID'];
      $result[ $row['ID'] ] = $row['IBLOCK_SECTION_ID'];
    }
    if ( $row['DEPTH_LEVEL'] == 3 ){
      $result[ $row['ID'] ] = $brands[ $row['IBLOCK_SECTION_ID'] ];
    }
  }

  return $result;
}

$arFilter = [
  'IBLOCK_ID' => 16,
  'PROPERTY_CML2_ARTICLE' => ['A164J204Y', 'C192J104Y', 'C192J204Y', 'C212J202Y','C212J204Y','C212J212Y','C214J215Y','DB00J335Y','M075J003Y','M075J004Y','M124J002Y','M124J003Y','M124J004Y','M010J002Y','Q985J001Y'],
];
$arSelect = ["ID", "IBLOCK_ID", "PROPERTY_CML2_ARTICLE", "IBLOCK_SECTION_ID"];

$rows = CIBlockElement::getList( [], $arFilter, false, false, $arSelect);
$sections = getSectionDictionary();

$result = [];

while( $row = $rows->GetNext() ){
  var_dump($row['IBLOCK_SECTION_ID']);
  if ( $sections[$row['IBLOCK_SECTION_ID']] != 932 ) continue;
  $result[] = $row['PROPERTY_CML2_ARTICLE_VALUE'];
}

var_dump($result);
// file_put_contents(
//   '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/configs/no_tnvd_list.json',
//   json_encode( $result )
// );
 ?>
