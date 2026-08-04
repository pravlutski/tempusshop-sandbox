<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);
if(!CModule::IncludeModule("iblock"))  return;
$arSelect = Array("ID", "IBLOCK_ID", "NAME", "DETAIL_PICTURE","PROPERTY_100","PROPERTY_123");
$arFilter = Array("IBLOCK_ID" => 16, "PROPERTY_282_VALUE" => 'В наличии');
$res = CIBlockElement::GetList(Array(), $arFilter, false, Array(), $arSelect);
while($ob = $res->GetNextElement()){
 $arFields = $ob->GetFields();
 foreach ($arFields['PROPERTY_100_VALUE'] as $key => $value) {
   $img[] = CFile::GetPath($value);
 }
  $arResult[] = [
    'img' => $img,
    'art' => $arFields['PROPERTY_123_VALUE']
  ];
  unset($img);
}

foreach ($arResult as $key => $value) {
  $i=1;
  foreach ($value['img'] as $ks => $vs) {
    $f = strtolower( pathinfo( $vs, PATHINFO_EXTENSION ));
    $i_new = '/var/www/bitrix/data/www/tempusshop.ru/phupdate_2/'.$value['art'].'@'.$i.'.' .$f;
    if (!copy('/var/www/bitrix/data/www/tempusshop.ru'. $vs, $i_new)) {
        echo "не удалось скопировать {$value['art']} \n";
    } else {
      echo 'ok';
    }
    $i = $i+1;
  }

}

?>
