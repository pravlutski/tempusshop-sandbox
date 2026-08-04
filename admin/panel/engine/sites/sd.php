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

$logFile = __DIR__ . '/exchange_log_price_activity.txt';

//wdhs
$CurDB = new DBPanel();
$arWhere[] = [
  'column' => 'code',
  'operator' => '=',
  'value' => 'PriceStockExchange'
];

function logMessage($message) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}


function clearLogFile() {
    global $logFile;
    file_put_contents($logFile, ''); // Очищаем содержимое файла
    //logMessage("Файл логов очищен. Начало нового сеанса обмена.");
}

function sendRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function sendRequestWithRetry($url, $data, $maxRetries = 3) {
    for ($i = 0; $i < $maxRetries; $i++) {
        $response = sendRequest($url, $data);
        if (strpos($response, '504 Gateway Time-out') === false) {
            return $response;
        }
        //logMessage("Попытка " . ($i + 1) . " не удалась. Ожидание перед повторной попыткой...");
        sleep(5 * ($i + 1));
    }
    //logMessage("Все попытки исчерпаны. Последний ответ: " . $response);
    return $response;
}

function sendElementsBatch($elementDataArray) {
    if (!empty($elementDataArray)) {
        $response = sendRequestWithRetry('https://tempus.ru/local/rest/exchange_price_activity.php', [
            'action' => 'updatePricesAndActivity',
            'data' => json_encode($elementDataArray)
        ]);
        //logMessage("Отправлена пачка из " . count($elementDataArray) . " элементов");
        return $response;
    }
    return null;
}

function fetchElements($sectionId) {
    $batchSize = 1000;
    $elementDataArray = [];
    $totalProcessed = 0;

    global $DB;

    $elements = ElementTable::getList([
        'filter' => ['IBLOCK_ID' => 16, 'IBLOCK_SECTION_ID' => $sectionId],
        'select' => ['ID', 'NAME', 'ACTIVE'],
    ])->fetchAll();

    foreach ($elements as $element) {
        $elementData = [
            'ID' => $element['ID'],
            'NAME' => $element['NAME'],
            'ACTIVE' => $element['ACTIVE'],
        ];

        // Получаем базовую цену
        $basePriceValue = CPrice::GetBasePrice($element['ID']);
        $elementData['BASE_PRICE'] = $basePriceValue['PRICE'];

        // Получаем цену BASE_BEL
        $belPrice = CPrice::GetList(
            array(),
            array(
                "PRODUCT_ID" => $element['ID'],
                "CATALOG_GROUP_ID" => 2
            )
        )->Fetch();
        $elementData['BASE_BEL'] = $belPrice['PRICE'];

        $quantity = CCatalogProduct::GetByID($element['ID']);
        $elementData['QUANTITY'] = $quantity['QUANTITY'];

        $props = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'CML2_ARTICLE']);

        if ($prop = $props->Fetch()) {
            $elementData['CML2_ARTICLE'] = $prop['VALUE'];

            // Проверяем наличие записи в ci_price
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
                        //logMessage("Резерв ({$reserved}) равен доступному количеству ({$count}) для артикула {$article}");
                    } else if ($reserved >= $availableBy) {
                        $elementData['AVAILABILITY_BY'] = '494';
                        //logMessage("Резерв ({$reserved}) больше доступного количества ({$count}) для артикула {$article}");
                    } else if ($reserved >= $count) {
                        $elementData['AVAILABILITY_BY'] = '493';
                        //logMessage("Резерв ({$reserved}) больше или равен количеству ({$count}) для артикула {$article}");
                    } else {
                        $elementData['AVAILABILITY_BY'] = '492';
                        //logMessage("Резерв ({$reserved}) меньше количества ({$count}) для артикула {$article}");
                    }
                } else {
                    // Если записи в ci_reserved нет, используем значение 492
                    $elementData['AVAILABILITY_BY'] = '492';
                    //logMessage("Не найдена запись в ci_reserved для артикула {$article}");
                }

                //logMessage("Найдена запись в ci_price для артикула {$article}");
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
                //logMessage("Не найдена запись в ci_price для артикула {$article}");
            }
        }

        $propsEnableRu = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'AVAILABILITY_RU']);
        if ($propEnableRu = $propsEnableRu->Fetch()) {
            $elementData['AVAILABILITY_RU'] = $propEnableRu['VALUE'];
        }

        $elementDataArray[] = $elementData;
        $totalProcessed++;

        if (count($elementDataArray) >= $batchSize) {
            //$response = sendElementsBatch($elementDataArray);
            file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/volume.txt", print_r($elementDataArray, true).PHP_EOL, FILE_APPEND);
            //logMessage("Обработано {$totalProcessed} элементов из раздела {$sectionId}");
            $elementDataArray = [];
        }
    }

    if (!empty($elementDataArray)) {
        //$response = sendElementsBatch($elementDataArray);
        file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/volume.txt", print_r($elementDataArray, true).PHP_EOL, FILE_APPEND);
        //logMessage("Обработано {$totalProcessed} элементов из раздела {$sectionId}");
    }

    $subSections = SectionTable::getList([
        'filter' => ['IBLOCK_ID' => 16, 'IBLOCK_SECTION_ID' => $sectionId],
        'select' => ['ID'],
    ])->fetchAll();


    foreach ($subSections as $subSection) {
        fetchElements($subSection['ID']);
    }
}

// Поменяйте ID раздела на нужные вам
$taskSection = [
    4555, 4530, 3219, 3218, 1886, 1887, 1888, 1889, 1890, 1891, 1892, 2133, 2134, 2136, 392, 393, 394, 2135, // Anne Klein
    638, // Armani Exchange
    558, // Bering
    381, // Calvin Klein
    562, // Candino
    4534, 3182, 2860, 1104, 1105, 224, 226, 227, 228, 229, 231, 232, // Casio
    233, // Certina,
    3225, // CIGA Design
    240, // Citizen
    554, // Daniel Klein
    252, // Diesel
    258, // DKNY
    269, // Emporio Armani
    274, // Fossil
    413, // Frederique Constant
    2935, // George Kini
    566, // Ingersoll
    2911, // Jowissa
    548, // Longines
    378, // Michael Kors
    283, // Orient
    355, // Q&Q
    428, // Raymond Weil
    444, // Seiko
    309, // Skagen
    321, // Timex
    332, // Tissot
    2284, // Tommy Hilfiger
    544, // Восток
    411, // Луч
//    2458, // подарочные сертификаты
//    371 // аксессуары
];


$timeStart = date('Y.m.d G:i:s');
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog.txt", print_r('START:'.$timeStart, true).PHP_EOL, FILE_APPEND);
foreach ($taskSection as $sectionId) {
    fetchElements($sectionId);
}
$timeEnd = date('Y.m.d G:i:s');
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog.txt", print_r('END:'.$timeEnd, true).PHP_EOL, FILE_APPEND);
?>
