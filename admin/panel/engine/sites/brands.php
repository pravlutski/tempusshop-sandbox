<?php

$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

die;

const NO_KEEP_STATISTIC = true;
const NOT_CHECK_PERMISSIONS = true;
const BASE_URL = "https://tempusshop.ru";

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

set_time_limit(0);
ini_set('memory_limit', '1512M');

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;

Loader::includeModule('iblock');
Loader::includeModule('panel.manager');

$arElemnts = [];
print_r('111');
function sendRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function fetchElements($sectionId,$mainSection) {
    $batchSize = 50;
    $totalProcessed = 0;
    if ($mainSection == 0) {
      $mainSection = $sectionId;
    }
    global $DB;
    global $arElemnts;
    $elements = ElementTable::getList([
        'filter' => ['IBLOCK_ID' => 16, 'IBLOCK_SECTION_ID' => $sectionId,"ACTIVE"=>"Y"],
        'select' => ['ID', 'NAME', 'ACTIVE'],
    ])->fetchAll();

    foreach ($elements as $element) {

        $elementData = [
            'ID' => $element['ID'],
            'NAME' => $element['NAME'],
            'ACTIVE' => $element['ACTIVE'],
            'SECTION' => $mainSection,
        ];

        // Получаем базовую цену
        $basePriceValue = CPrice::GetBasePrice($element['ID']);

        // Получаем цену BASE_BEL
        $belPrice = CPrice::GetList(
            array(),
            array(
                "PRODUCT_ID" => $element['ID'],
                "CATALOG_GROUP_ID" => 2
            )
        )->Fetch();

        $quantity = CCatalogProduct::GetByID($element['ID']);
        $elementData['QUANTITY'] = $quantity['QUANTITY'];

        $props = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'CML2_ARTICLE']);

        if ($prop = $props->Fetch()) {
            $elementData['CML2_ARTICLE'] = $prop['VALUE'];


            $propsBrends = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'BRAND']);
            if ($propBrend = $propsBrends->Fetch()) {
                  $elementData['BRAND'] = $propBrend['VALUE'];
            }

            $article = $DB->ForSql($elementData['CML2_ARTICLE']);
            $strSql = "SELECT * FROM `ci_price` WHERE `supplier_id` = '44' AND `model` = '{$article}'";
            $rsData = $DB->Query($strSql);

            if ($rsData && $arData = $rsData->Fetch()) {
                // Получаем count из ci_price
                $count = intval($arData['count']);

                // Проверяем наличие записи в ci_reserved
                $strSqlReserved = "SELECT * FROM `ci_reserved` WHERE `ARTICLE` = '{$article}'";
                $rsDataReserved = $DB->Query($strSqlReserved);

                if ($rsDataReserved && $arDataReserved = $rsDataReserved->Fetch()) {
                    $reserved = intval($arDataReserved['RESERVED_s2']);
                    $availableBy = intval($arDataReserved['AVAILABLE_BY']);

                    if ($reserved === $availableBy) {
                        $propsEnableBy = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'AVAILABILITY_BY']);
                        if ($propEnableBy = $propsEnableBy->Fetch()) {
                            $elementData['AVAILABILITY_BY'] = $propEnableBy['VALUE'];
                        }
                    } else if ($reserved >= $availableBy) {
                        $elementData['AVAILABILITY_BY'] = '494';
                    } else if ($reserved >= $count) {
                        $elementData['AVAILABILITY_BY'] = '493';
                    } else {
                        $elementData['AVAILABILITY_BY'] = '492';
                    }
                } else {
                    // Если записи в ci_reserved нет, используем значение 492
                    $elementData['AVAILABILITY_BY'] = '492';
                }

            } else {
                // Если запись не найдена, используем стандартную логику
                $propsEnableBy = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'AVAILABILITY_BY']);
                if ($propEnableBy = $propsEnableBy->Fetch()) {
                    if($propEnableBy['VALUE'] === 492) {
                        $elementData['AVAILABILITY_BY'] = 493;
                    } else {
                        $elementData['AVAILABILITY_BY'] = $propEnableBy['VALUE'];
                    }
                }
            }
        }

        $propsEnableRu = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'AVAILABILITY_RU']);
        if ($propEnableRu = $propsEnableRu->Fetch()) {
            $elementData['AVAILABILITY_RU'] = $propEnableRu['VALUE'];
        }

        $arElemnts[] = $elementData;
        $totalProcessed++;
    }

    $subSections = SectionTable::getList([
        'filter' => ['IBLOCK_ID' => 16, 'IBLOCK_SECTION_ID' => $sectionId],
        'select' => ['ID'],
    ])->fetchAll();


    foreach ($subSections as $subSection) {
        fetchElements($subSection['ID'],$mainSection);
    }


}

// Поменяйте ID раздела на нужные вам
// $taskSection = [
//     4555, 4530, 3219, 3218, 1886, 1887, 1888, 1889, 1890, 1891, 1892, 2133, 2134, 2136, 392, 393, 394, 2135, // Anne Klein
//     638, // Armani Exchange
//     558, // Bering
//     381, // Calvin Klein
//     562, // Candino
//     4534, 3182, 2860, 1104, 1105, 224, 226, 227, 228, 229, 231, 232, // Casio
//     233, // Certina,
//     3225, // CIGA Design
//     240, // Citizen
//     554, // Daniel Klein
//     252, // Diesel
//     258, // DKNY
//     269, // Emporio Armani
//     274, // Fossil
//     413, // Frederique Constant
//     2935, // George Kini
//     566, // Ingersoll
//     2911, // Jowissa
//     548, // Longines
//     378, // Michael Kors
//     283, // Orient
//     355, // Q&Q
//     428, // Raymond Weil
//     444, // Seiko
//     309, // Skagen
//     321, // Timex
//     332, // Tissot
//     2284, // Tommy Hilfiger
//     544, // Восток
//     411 // Луч
// ];
$taskSection = [];

$res = CIBlockSection::GetList(
    ['SORT' => 'ASC'],
    [
        'IBLOCK_ID' => 16,
        'SECTION_ID' => 932,
        'ACTIVE' => 'Y'
    ],
    false,
    ['ID', 'NAME', 'CODE', 'SECTION_PAGE_URL']
);

while ($section = $res->GetNext()) {
    $taskSection[] = $section['ID'];

    $resL2 = CIBlockSection::GetList(
        ['SORT' => 'ASC'],
        [
            'IBLOCK_ID' => 16,
            'SECTION_ID' => $section['ID'],
        ],
        false,
        ['ID']
    );

    while ($sectionL2 = $resL2->GetNext()) {
        $taskSection[] = $sectionL2['ID'];
    }
}
//
$CurDB = new DBPanel();
$arWhere[] = [
  'column' => 'code',
  'operator' => '=',
  'value' => 'BrandExclude'
];

$timeStart = date('Y.m.d G:i:s');
$addArray = [
  'status' => 'PROCESS',
  'status_text' => 'Запуск скрипта',
  'percent' => 0,
  'time_start' => $timeStart,
];
$CurDB->update('sites_agents', $addArray, $arWhere);

sleep(5);

$addArray = [
  'status_text' => 'Получаем разделы',
  'percent' => 20,
];
$CurDB->update('sites_agents', $addArray, $arWhere);

$fullCount = count($taskSection);
$onePercent = 70 / $fullCount;
$newproc = 20;

foreach ($taskSection as $sectionId) {
    $newproc = $newproc + $onePercent;
    $addArray = [
      'status_text' => 'Обработка данных',
      'percent' => round($newproc,2),
    ];
    $CurDB->update('sites_agents', $addArray, $arWhere);

    fetchElements($sectionId,0);
}

foreach ($arElemnts as $value) {
  // if ($value['AVAILABILITY_BY'] != '494' && ($value['QUANTITY'] == '50' || $value['QUANTITY'] == '500')) {
  if ($value['AVAILABILITY_BY'] != '494') {
    $arResult[$value['BRAND']]['BY'][] = $value['CML2_ARTICLE'];
  }
  if ($value['AVAILABILITY_RU'] != '514') {
  // if ($value['AVAILABILITY_RU'] != '514' && ($value['QUANTITY'] == '50' || $value['QUANTITY'] == '500')) {
    $arResult[$value['BRAND']]['RU'][] = $value['CML2_ARTICLE'];
  }
}

$exBrand = array();

$arSelect = array(
    "ID",
    "NAME"
);
$arFilter = array(
    "IBLOCK_ID" => 11,
    // "ACTIVE" => "Y",
);

$res = CIBlockElement::GetList(
    array("SORT" => "ASC"),
    $arFilter,
    false,
    false,
    $arSelect
);

while ($ob = $res->GetNextElement()) {

      $arFields = $ob->GetFields();
      $arBrnads[$arFields['ID']] = $arFields['NAME'];

}

$CurDB->Query("DELETE FROM sites_brand_exclude WHERE 1=1", false, $err_mess.__LINE__);

foreach ($arBrnads as $key => $value) {
  print_r($value);
  print_r('#');
  if (intval($key) == 206671) continue;

  if (!isset($arResult[$key]['RU'])) {
    $exBrand[] = ['brand_id' => $key, 'name' => $value, 'site' => 'RU'];
  }

  if (!isset($arResult[$key]['BY'])) {
    $exBrand[] = ['brand_id' => $key, 'name' => $value, 'site' => 'BY'];
  }

  if (isset($arResult[$key]['RU']) && count($arResult[$key]['RU']) <= 4) {
    $exBrand[] = ['brand_id' => $key, 'name' => $value, 'site' => 'RU'];
  }

  if (isset($arResult[$key]['BY']) && count($arResult[$key]['BY']) <= 4) {
    $exBrand[] = ['brand_id' => $key, 'name' => $value, 'site' => 'BY'];
  }

}


$CurDB->insert('sites_brand_exclude', $exBrand );



file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/result.txt", print_r(json_encode($arResult), true));

$timeEnd = date('Y.m.d G:i:s');
$addArray = [
  'status' => 'COMPLETED',
  'status_text' => 'Скрипт закончил работу',
  'percent' => 100,
  'time_end' => $timeEnd,
];
$CurDB->update('sites_agents', $addArray, $arWhere);


?>
