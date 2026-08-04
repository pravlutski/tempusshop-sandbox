<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
// CIBlockElement::SetPropertyValueCode($arItem["ID"], "NAME_WB_MP", array('VALUE' => $dsc2));
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$arFilter = Array(
  "IBLOCK_ID"	=> 16,
  "!PROPERTY_DESC_RICH_OZON" => false,
  "PROPERTY_DESCRIPTION_WB" => false
);

$arSelect = array("ID", "IBLOCK_ID", "PROPERTY_DESC_RICH_OZON", "PROPERTY_DESCRIPTION_WB");
$rs = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );

//Формируем массив и фильтруем по количеству дней на складе
$descriptions = [];
while($art = $rs->GetNext()){
  if ($art['PROPERTY_DESC_RICH_OZON_VALUE']['TEXT'] != "."){
    $descriptions[] = [
    'id' => $art['ID'],
    'desc_oz' => $art['PROPERTY_DESC_RICH_OZON_VALUE']['TEXT'],
    'desc_wb' => $art['PROPERTY_DESCRIPTION_WB_VALUE']
  ];}
}
foreach ($descriptions as $value) {
  CIBlockElement::SetPropertyValueCode($value["id"], "DESCRIPTION_WB", array('VALUE' => $value['desc_oz']));
}
// var_dump( count($descriptions) );
// for ($i = 0; $i < 10; $i++){
//   var_dump($descriptions[$i]);
// }
 ?>
