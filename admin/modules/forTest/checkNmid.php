<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$arFilter = Array(
	"IBLOCK_ID"	=> 16,
  "!PROPERTY_PROP_MAXYSS_CARDID_WB" => false
);
$arSelect = array("ID", "IBLOCK_ID", "PROPERTY_PROP_MAXYSS_CARDID_WB", "PROPERTY_PROP_MAXYSS_NMID_CREATED_WB");

$rs = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );

$imtGroups = [];
while ( $art = $rs->getNext() ){
  foreach ($art['PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_DESCRIPTION'] as $key => $value) {
    if ($value == 'WR') {
      $nmid = $art['PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_VALUE'][$key];
    }
  }
  foreach ($art['PROPERTY_PROP_MAXYSS_CARDID_WB_DESCRIPTION'] as $key => $value) {
    if ($value == 'WR') {
      $cardid = $art['PROPERTY_PROP_MAXYSS_CARDID_WB_VALUE'][$key];
    }
  }
  $imtGroups[$cardid] = $nmid;
}
print_r( $imtGroups);

 ?>
