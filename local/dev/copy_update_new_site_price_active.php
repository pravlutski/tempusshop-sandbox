<?php

$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

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

$logFile = __DIR__ . '/exchange_log_price_activity.txt';

function logMessage($message) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}


function clearLogFile() {
    global $logFile;
    file_put_contents($logFile, ''); // Очищаем содержимое файла
    logMessage("Файл логов очищен. Начало нового сеанса обмена.");
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
        logMessage("Попытка " . ($i + 1) . " не удалась. Ожидание перед повторной попыткой...");
        sleep(5 * ($i + 1));
    }
    logMessage("Все попытки исчерпаны. Последний ответ: " . $response);
    return $response;
}

function fetchElements($sectionId) {
    // Получаем элементы текущего раздела
    $elements = ElementTable::getList([
        'filter' => ['IBLOCK_ID' => 16, 'IBLOCK_SECTION_ID' => $sectionId],
        'select' => ['ID', 'NAME'],
    ])->fetchAll();

    $elementDataArray = [];

    foreach ($elements as $element) {
        $elementData = [
            'ID' => $element['ID'],
            'NAME' => $element['NAME'],
        ];

        // Получаем базовую цену
        $basePriceValue = CPrice::GetBasePrice($element['ID']);
        $elementData['BASE_PRICE'] = $basePriceValue['PRICE'];

        // Получаем цену BASE_BEL
        $belPrice = CPrice::GetList(
            array(),
            array(
                "PRODUCT_ID" => $element['ID'],
                "CATALOG_GROUP_ID" => 2 // ID типа цены BASE_BEL
            )
        )->Fetch();
        $elementData['BASE_BEL'] = $belPrice['PRICE'];

        $quantity = CCatalogProduct::GetByID($element['ID']);
        $elementData['QUANTITY'] = $quantity['QUANTITY'];

        $props = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'CML2_ARTICLE']);
        if ($prop = $props->Fetch()) {
            $elementData['CML2_ARTICLE'] = $prop['VALUE'];
        }

        $propsEnableRu = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'AVAILABILITY_RU']);
        if ($propEnableRu = $propsEnableRu->Fetch()) {
            $elementData['AVAILABILITY_RU'] = $propEnableRu['VALUE'];
        }

        // Добавляем получение AVAILABILITY_BY
        $propsEnableBy = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'AVAILABILITY_BY']);
        if ($propEnableBy = $propsEnableBy->Fetch()) {
            $elementData['AVAILABILITY_BY'] = $propEnableBy['VALUE'];
        }

        $elementDataArray[] = $elementData;
    }

    if (!empty($elementDataArray)) {
        $response = sendRequestWithRetry('https://tempus.ru/local/rest/exchange_price_activity.php', [
            'action' => 'updatePricesAndActivity',
            'data' => json_encode($elementDataArray)
        ]);
        logMessage("Отправлено " . count($elementDataArray) . " элементов: " . $response);
    }

    // Рекурсивно обрабатываем подразделы
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
    4534, 3182, 2860, 1104, 1105, 224, 226, 227, 228, 229, 231, 232, 206, 233, 240, 252, 258, 269, 274, 283, 309, 317,
    321, 332, 355, 378, 380, 382, 411, 413, 427, 428, 444, 451, 544, 548, 554, 558, 562, 566, 569, 576, 638, 661, 1009,
    2266, 2284, 2285, 2286, 2287, 2288, 2906, 2907, 2911, 2919, 2920, 2921, 2922, 2931, 2935, 3193, 3225, 4546, 4550,
    4549, 4548, 4547, 611, 4504, 1092, 1093
];

clearLogFile();

foreach ($taskSection as $sectionId) {
    logMessage("Начало обмена данными по разделу {$sectionId}");
    fetchElements($sectionId);
    logMessage("Завершение обмена данными по разделу {$sectionId}");
}

logMessage("Обмен данными завершен");

?>