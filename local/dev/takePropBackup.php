<?php

$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

const NO_KEEP_STATISTIC = true;
const NOT_CHECK_PERMISSIONS = true;
const BASE_URL = "https://tempusshop.ru";

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

set_time_limit(0);
ini_set('memory_limit', '4048M');

use Bitrix\Main\Loader;
use Bitrix\Main\Entity;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');
Loader::includeModule('panel.manager');

$logFile = __DIR__ . '/exchange_log.txt';

//wdhs
$CurDB = new DBPanel();

$tmp = file_get_contents("/var/www/bitrix/data/www/tempus.ru/local/rest/props.txt");
$arResult = json_decode($tmp,true);
print_r($arResult);


foreach ($arResult as $oldArray) {
  file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/take.log',print_r($oldArray,true). PHP_EOL, FILE_APPEND);
  file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/take.log',print_r('-----',true). PHP_EOL, FILE_APPEND);
  $arSelect = Array("ID", "PROPERTY_CML2_ARTICLE", "XML_ID");

  $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
      "PROPERTY_CML2_ARTICLE" => $oldArray['PROPERTY_CML2_ARTICLE_VALUE']
  );
  $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){
      if (isset($el['ID']) && !empty($el['ID']) && !empty($oldArray['PROPERTY_POPULAR_TAG_VALUES'])) {
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/take.log',print_r('GO',true). PHP_EOL, FILE_APPEND);
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/take.log',print_r('-----',true). PHP_EOL, FILE_APPEND);

        $enumIds = [];
        foreach ($oldArray['PROPERTY_POPULAR_TAG_VALUES'] as $ppp) {
            $propertyEnums = CIBlockPropertyEnum::GetList(
                array(),
                array(
                    "IBLOCK_ID" => 16,
                    "CODE" => "POPULAR_TAG",
                    "VALUE" => $ppp['VALUE']
                )
            );
            if ($enum = $propertyEnums->Fetch()) {
                $enumIds[] = $enum["ID"];
            }
        }
        if (!empty($enumIds)) {
          CIBlockElement::SetPropertyValuesEx($el['ID'], 16, array('POPULAR_TAG' => $enumIds));
          file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/take.log',print_r('ОБНОВЛЯЕМ',true). PHP_EOL, FILE_APPEND);
          file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/take.log',print_r($enumIds,true). PHP_EOL, FILE_APPEND);
          file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/take.log',print_r('-----',true). PHP_EOL, FILE_APPEND);
        }

        unset($enumIds);
    }
  }
}
