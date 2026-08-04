<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule('panel.manager');

use Bitrix\Main\Loader;
use Bitrix\Sale;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');
Loader::includeModule('sale');

GLOBAL $DB;

require_once '/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';

$excel = PHPExcel_IOFactory::load('av_import.xlsx');
foreach($excel ->getWorksheetIterator() as $worksheet) {
 $lists[] = $worksheet->toArray();
}
$list = $lists[0];

$swArray = array();
foreach ($list as $key => $value) {
  $swArray[$value[0]] = [
    "Gender" => $value[5],
    "Color" => $value[3],
    "Brand" => $value[4],
    "StrapType" => $value[2],
    "Mechanism" => $value[1]
  ];
}
$swArray2['1002'] = $swArray['1002'];
print_r($swArray);
foreach ($swArray as $id => $v) {
  $DB->Update("b_iblock_element_prop_s16", array("PROPERTY_3081" => "'".serialize($v)."'"), "WHERE IBLOCK_ELEMENT_ID = '$id'", $err_mess.__LINE__);
}
