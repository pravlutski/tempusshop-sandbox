<?php

$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
die;
const NO_KEEP_STATISTIC = true;
const NOT_CHECK_PERMISSIONS = true;
const BASE_URL = "https://tempusshop.ru";

require($DOCUMENT_ROOT. "/bitrix/modules/main/include/prolog_before.php");

set_time_limit(0);
ini_set('memory_limit', '1512M');

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;

Loader::includeModule('iblock');
Loader::includeModule('panel.manager');

$logFile = __DIR__ . '/exchange_log.txt';

function logMessage($message) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
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

function clearLogFile() {
    global $logFile;
    file_put_contents($logFile, ''); // Очищаем содержимое файла
    //logMessage("Файл логов очищен. Начало нового сеанса обмена.");
}

function sendSections($parentSectionId = 932) {
    $sections = SectionTable::getList([
        'filter' => ['IBLOCK_ID' => 16, 'IBLOCK_SECTION_ID' => $parentSectionId],
        'select' => ['ID', 'NAME', 'CODE', 'DESCRIPTION', 'PICTURE', 'DETAIL_PICTURE', 'UF_*'],
    ])->fetchAll();

    foreach ($sections as $section) {
        $sectionData = [
            'NAME' => $section['NAME'],
            'CODE' => $section['CODE'],
            'PARENT_ID' => $parentSectionId,
            'DESCRIPTION' => $section['DESCRIPTION'],
            'PICTURE' => CFile::GetFileArray($section['PICTURE']),
            'DETAIL_PICTURE' => CFile::GetFileArray($section['DETAIL_PICTURE']),
            'UF_TAGS' => $section['NAME'],
            'UF_HELP_TEXT' => $section['ID'],
            'META_TITLE' => $section['UF_META_TITLE'],
            'META_DESCRIPTION' => $section['UF_META_DESCRIPTION'],
            'META_KEYWORDS' => $section['UF_META_KEYWORDS'],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'big_array_');
        file_put_contents($tempFile, json_encode($sectionData));
        exec("php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/exchangeJSONSubSript.php '" . $tempFile . "' addSection");

        file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog.txt", print_r('###', true).PHP_EOL, FILE_APPEND);
        file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog.txt", print_r(date('d.m.Y H:i:s'), true).PHP_EOL, FILE_APPEND);
        file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog.txt", print_r('PROC: addSection', true).PHP_EOL, FILE_APPEND);
        file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog.txt", print_r($sectionData, true).PHP_EOL, FILE_APPEND);

        sendSections($section['ID']);
    }
}


/**
 * @param $sectionId
 * @param $page
 * @param $pageSize
 * @return void
 * @throws ArgumentException
 * @throws ObjectPropertyException
 * @throws SystemException
 */

 function sendElements($sectionId, $page = 1, $pageSize = 50): void
 {
     $elements = ElementTable::getList([
         'filter' => [
             'IBLOCK_ID' => 16,
             'IBLOCK_SECTION_ID' => $sectionId,
         ],
         'order' => ['DATE_CREATE' => 'DESC'],
         'select' => ['ID', 'NAME', 'CODE', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'],
         'limit' => $pageSize,
         'offset' => ($page - 1) * $pageSize
     ])->fetchAll();

     $elementDataArray = [];

     foreach ($elements as $element) {

 //        $availability = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'AVAILABILITY_RU'])->Fetch();
         $elementData = [
             'NAME' => $element['NAME'],
             'CODE' => $element['CODE'],
             'PREVIEW_TEXT' => $element['PREVIEW_TEXT'],
             'DETAIL_TEXT' => $element['DETAIL_TEXT'],
             'SECTION_ID' => $sectionId,
             'SECTIONS' => [],
             'PREVIEW_PICTURE' => null,
             'DETAIL_PICTURE' => null,
             'PROPERTIES' => []
         ];

         // Обработка картинок
         $previewPictureArray = CFile::GetFileArray($element['PREVIEW_PICTURE']);
         if ($previewPictureArray) {
             $previewPictureArray['SRC'] = BASE_URL . $previewPictureArray['SRC'];
             $elementData['PREVIEW_PICTURE'] = $previewPictureArray;
         }

         $detailPictureArray = CFile::GetFileArray($element['DETAIL_PICTURE']);
         if ($detailPictureArray) {
             $detailPictureArray['SRC'] = BASE_URL . $detailPictureArray['SRC'];
             $elementData['DETAIL_PICTURE'] = $detailPictureArray;
         }

         // Обработка свойств
         $props = CIBlockElement::GetProperty(16, $element['ID']);
         while ($prop = $props->Fetch()) {
             $propInfo = CIBlockProperty::GetByID($prop['ID'])->Fetch();
             if ($propInfo['PROPERTY_TYPE'] == 'L') {
                 if ($propInfo['MULTIPLE'] == 'Y') {
                     if (!isset($elementData['PROPERTIES'][$prop['CODE']])) {
                         $elementData['PROPERTIES'][$prop['CODE']] = [];
                     }
                     $enum = CIBlockPropertyEnum::GetByID($prop['VALUE']);
                     $elementData['PROPERTIES'][$prop['CODE']][] = $enum['VALUE'];
                 } else {
                     $enum = CIBlockPropertyEnum::GetByID($prop['VALUE']);
                     $elementData['PROPERTIES'][$prop['CODE']] = $enum['VALUE'];
                 }
             } elseif ($propInfo['PROPERTY_TYPE'] == 'E') {
                 if ($propInfo['MULTIPLE'] == 'Y') {
                     if (!isset($elementData['PROPERTIES'][$prop['CODE']])) {
                         $elementData['PROPERTIES'][$prop['CODE']] = [];
                     }
                     $linkedElement = CIBlockElement::GetByID($prop['VALUE'])->Fetch();
                     $elementData['PROPERTIES'][$prop['CODE']][] = $linkedElement['NAME'];
                 } else {
                     $linkedElement = CIBlockElement::GetByID($prop['VALUE'])->Fetch();
                     $elementData['PROPERTIES'][$prop['CODE']] = $linkedElement['NAME'];
                 }
             } else {
                 if ($propInfo['MULTIPLE'] == 'Y') {
                     if (!isset($elementData['PROPERTIES'][$prop['CODE']])) {
                         $elementData['PROPERTIES'][$prop['CODE']] = [];
                     }
                     $elementData['PROPERTIES'][$prop['CODE']][] = $prop['VALUE'];
                 } else {
                     $elementData['PROPERTIES'][$prop['CODE']] = $prop['VALUE'];
                 }
             }
         }

         // Обработка MORE_PHOTO
         $morePhotoValues = [];
         $morePhotos = CIBlockElement::GetProperty(16, $element['ID'], array(), array('CODE' => 'MORE_PHOTO'));
         while ($morePhoto = $morePhotos->Fetch()) {
             $photoArray = CFile::GetFileArray($morePhoto['VALUE']);
             if ($photoArray) {
                 $photoArray['SRC'] = BASE_URL . $photoArray['SRC'];
                 $morePhotoValues[] = $photoArray;
             }
         }
         $elementData['PROPERTIES']['MORE_PHOTO'] = $morePhotoValues;

         // Добавление цены и количества
         $basePriceValue = CPrice::GetBasePrice($element['ID']);
         $elementData['BASE_PRICE'] = $basePriceValue['PRICE'];

         $quantity = CCatalogProduct::GetByID($element['ID']);
         $elementData['QUANTITY'] = $quantity['QUANTITY'];

         $elementData['ACTIVE'] = ($quantity > 0) ? 'Y' : 'N';

         // Получаем все разделы элемента
         $db_old_groups = CIBlockElement::GetElementGroups($element['ID'], true);
         while($ar_group = $db_old_groups->Fetch()) {
             $elementData['SECTIONS'][] = $ar_group['ID'];
         }

 //            $viewCount = CIBlockElement::GetProperty(16, $element['ID'], array("sort" => "asc"), array("CODE"=>"SHOW_COUNTER"))->Fetch();
         $viewCount = CIBlockElement::GetByID($element['ID']);
         if($arView = $viewCount->GetNext()){
             $elementData['SHOW_COUNTER'] = $arView["SHOW_COUNTER"];
         }

         $elementDataArray[] = $elementData;
         /* отладка */
         // logMessage(print_r($elementData, true));
         if ($quantity <= 0) {
             logMessage("Элемент {$element['ID']} деактивирован (нулевой или отрицательный остаток)");
         }
     }

     if (!empty($elementDataArray)) {
         $tempFile = tempnam(sys_get_temp_dir(), 'big_array_');
         file_put_contents($tempFile, json_encode($elementDataArray));
         exec("php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/exchangeJSONSubSript.php '" . $tempFile . "' addElements");

         file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog.txt", print_r('###', true).PHP_EOL, FILE_APPEND);
         file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog.txt", print_r(date('d.m.Y H:i:s'), true).PHP_EOL, FILE_APPEND);
         file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog.txt", print_r('PROC: addElements', true).PHP_EOL, FILE_APPEND);
         file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog.txt", print_r($elementDataArray, true).PHP_EOL, FILE_APPEND);

         sendElements($sectionId, $page + 1, $pageSize);
     }

     // Обработка подразделов
     $subsections = SectionTable::getList([
         'filter' => ['IBLOCK_ID' => 16, 'IBLOCK_SECTION_ID' => $sectionId],
         'select' => ['ID']
     ])->fetchAll();

     foreach ($subsections as $subsection) {
         sendElements($subsection['ID']);
     }
 }


$request = [];
foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $request[$k] = $v;
}
if(!$request["create_elements"] && $_REQUEST["create_elements"]){
	$request["create_elements"] = $_REQUEST["create_elements"];
}

if($request["create_elements"]){
	$ids = explode(",", $request["create_elements"]);
	//logMessage("Оправляем товары с контента " . print_r($ids, true));
	file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/dev/exchange_content.txt", print_r($ids, true), 8);
	sendElements(false, $ids);
	die;
}


// Поменяйте ID раздела на нужные вам
$taskSection = [
    // 4555, 4530, 3219, 3218, 1886, 1887, 1888, 1889, 1890, 1891, 1892, 2133, 2134, 2136, 392, 393, 394, 2135, // Anne Klein
    // 638, // Armani Exchange
    // 558, // Bering
    // 381, // Calvin Klein
    // 562, // Candino
    // 4534, 3182, 2860, 1104, 1105, 224, 226, 227, 228, 229, 231, 232, // Casio
    // 233, // Certina,
    // 3225, // CIGA Design
    // 240, // Citizen
    // 554, // Daniel Klein
    // 252, // Diesel
    // 258, // DKNY
    // 269, // Emporio Armani
    // 274, // Fossil
    // 413, // Frederique Constant
    // 2935, // George Kini
    // 566, // Ingersoll
    // 2911, // Jowissa
    // 548, // Longines
    // 378, // Michael Kors
    // 283, // Orient
    // 355, // Q&Q
    // 428, // Raymond Weil
    // 444, // Seiko
    // 309, // Skagen
    // 321, // Timex
    // 332, // Tissot
    // 2284, // Tommy Hilfiger
    // 544, // Восток
    411 // Луч
];



file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/workFlow.txt", print_r('###', true).PHP_EOL, FILE_APPEND);
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/workFlow.txt", print_r('START', true).PHP_EOL, FILE_APPEND);
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/workFlow.txt", print_r(date('d.m.Y H:i:s'), true).PHP_EOL, FILE_APPEND);
foreach ($taskSection as $item) {
    sendElements($item);
}
