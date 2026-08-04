<?php

$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

const NO_KEEP_STATISTIC = true;
const NOT_CHECK_PERMISSIONS = true;
const BASE_URL = "https://tempusshop.ru";

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

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
        logMessage("Попытка " . ($i + 1) . " не удалась. Ожидание перед повторной попыткой...");
        sleep(5 * ($i + 1));
    }
    logMessage("Все попытки исчерпаны. Последний ответ: " . $response);
    return $response;
}

function clearLogFile() {
    global $logFile;
    file_put_contents($logFile, ''); // Очищаем содержимое файла
    logMessage("Файл логов очищен. Начало нового сеанса обмена.");
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

        $response = sendRequestWithRetry('https://tempus.ru/local/rest/exchange.php', [
            'action' => 'addSection',
            'data' => json_encode($sectionData)
        ]);

        logMessage("Отправлен раздел {$section['ID']}: " . $response);

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
function sendElements($sectionId, $elementIDs = false, $page = 1, $pageSize = 50): void
{
	$mode = "sections";
	if($elementIDs && is_array($elementIDs) && count($elementIDs)){
		$arFilter = [
            'IBLOCK_ID' => 16,
            'ID' => $elementIDs,
        ];
		$mode = "elements";
	}else{
		$arFilter = [
            'IBLOCK_ID' => 16,
            'ID' => $sectionId,
        ];
	}
	
    $elements = ElementTable::getList([
        'filter' => $arFilter,
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

        $elementData['ACTIVE'] = ($quantity > 0 || $mode == "elements") ? 'Y' : 'N';

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
        $response = sendRequestWithRetry('https://tempus.ru/local/rest/exchange.php', [
            'action' => 'addElements',
            'data' => json_encode($elementDataArray)
        ]);
        logMessage("Отправлено " . count($elementDataArray) . " элементов: " . $response);
		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/dev/exchange_content.txt", print_r([$elementDataArray, $response], true), 8);
        // Отправляем следующую страницу элементов
        sendElements($sectionId, $elementIDs, $page + 1, $pageSize);
    }
	
	if($mode == "sections"){
		// Обработка подразделов
		$subsections = SectionTable::getList([
			'filter' => ['IBLOCK_ID' => 16, 'IBLOCK_SECTION_ID' => $sectionId],
			'select' => ['ID']
		])->fetchAll();

		foreach ($subsections as $subsection) {
			sendElements($subsection['ID'], $elementIDs);
		}
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
	logMessage("Оправляем товары с контента " . print_r($ids, true));
	file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/dev/exchange_content.txt", print_r($ids, true), 8);
	sendElements(false, $ids);
	die;
}

/**
 * @param $chatId
 * @param $text
 * @return void
 */
function sendMessage($chatId, $text): void
{
    $token = "1154557048:AAEsX62oYXar0GcGNVOmgfcdk-zp3cTIy-Q";


    $response = array(
        'chat_id' => $chatId,
        'text' => $text
    );

    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
}

// Поменяйте ID раздела на нужные вам
$taskSection = [
    2055
];

//wdhs
$CurDB = new DBPanel();
$arWhere[] = [
  'column' => 'code',
  'operator' => '=',
  'value' => 'Exchange'
];

$timeStart = date('Y.m.d G:i:s');
$addArray = [
  'status' => 'PROCESS',
  'status_text' => 'Инициирован обмен',
  'percent' => 0,
  'time_start' => $timeStart,
];
$CurDB->update('sites_agents', $addArray, $arWhere);
sleep(20);
$addArray = [
  'status_text' => 'Получаем данные о разделах',
  'percent' => 20,
];
$CurDB->update('sites_agents', $addArray, $arWhere);

$fullCount = count($taskSection);
$onePercent = 80 / $fullCount;
$newproc = 20;


foreach ($taskSection as $item) {
//    clearLogFile(); // очистка файл лога
    // Запуск процесса обмена
    logMessage("Начало обмена данными по разделу {$item}");

    $message = "Начало обмена данными по разделу {$item}";
    sendMessage(344031842, $message);

    sendElements($item);

    $newproc = $newproc + $onePercent;
    $addArray = [
      'status_text' => 'Обмен товарами',
      'percent' => round($newproc,2),
    ];
    $CurDB->update('sites_agents', $addArray, $arWhere);

    $message = "Завершение обмена данными по разделу {$item}";
    sendMessage(344031842, $message);
}

$timeEnd = date('Y.m.d G:i:s');
$addArray = [
  'status' => 'COMPLETED',
  'status_text' => 'Обмен завршен',
  'percent' => 100,
  'time_end' => $timeEnd,
];
$CurDB->update('sites_agents', $addArray, $arWhere);
//sendSections();

logMessage("Обмен данными завершен");
