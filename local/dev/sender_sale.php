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
use Bitrix\Main\Entity;
use Bitrix\Iblock\ElementTable;

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
    file_put_contents($logFile, '');
    logMessage("Файл логов очищен. Начало нового сеанса обмена.");
}


$query = new Entity\Query(Bitrix\Iblock\SectionElementTable::getEntity());

$query->registerRuntimeField(
    'ELEMENT',
    array(
        'data_type' => 'Bitrix\Iblock\ElementTable',
        'reference' => array('=this.IBLOCK_ELEMENT_ID' => 'ref.ID'),
        'join_type' => 'INNER'
    )
);

$query->setSelect([
    'ID' => 'IBLOCK_ELEMENT_ID',
    'NAME' => 'ELEMENT.NAME',
    'CODE' => 'ELEMENT.CODE'
])
    ->setFilter([
        'IBLOCK_SECTION_ID' => 4541,
        'ELEMENT.IBLOCK_ID' => 16
    ])
    ->setGroup(['IBLOCK_ELEMENT_ID'])
    ->setLimit(0);

$result = $query->exec();
$elementsList = $result->fetchAll();

function sendElements($elementsList) {


    $elementDataArray = [];

    foreach ($elementsList as $key => $element) {
        $elementData = [
            'ID' => $element['ID'],
            'NAME' => $element['NAME'],
            'CODE' => $element['CODE'],
            'SECTIONS' => [],
        ];

        // Получаем артикул товара
        $articleProp = CIBlockElement::GetProperty(16, $element['ID'], array("sort" => "asc"), array("CODE"=>"CML2_ARTICLE"))->Fetch();
        $elementData['CML2_ARTICLE'] = $articleProp['VALUE'];

        // Получаем все разделы элемента
        $db_old_groups = CIBlockElement::GetElementGroups($element['ID'], true);
        while($ar_group = $db_old_groups->Fetch()) {
            $elementData['SECTIONS'][] = $ar_group['ID'];
        }

        $elementDataArray[] = $elementData;
    }

    if (!empty($elementDataArray)) {
        $response = sendRequestWithRetry('https://tempus.ru/local/rest/exchange-sale.php', [
            'action' => 'updateElementSections',
            'data' => json_encode($elementDataArray)
        ]);
        logMessage("Отправлено " . count($elementDataArray) . " элементов: " . $response);

    }
}


$taskSection = [
    4 => 1192,
];

//wdhs
$CurDB = new DBPanel();
$arWhere[] = [
  'column' => 'code',
  'operator' => '=',
  'value' => 'SuperSaleRu'
];

$timeStart = date('Y.m.d G:i:s');
$addArray = [
  'status' => 'PROCESS',
  'status_text' => 'Инициирован обмен',
  'percent' => 0,
  'time_start' => $timeStart,
];
$CurDB->update('sites_agents', $addArray, $arWhere);

$addArray = [
  'status_text' => 'Получаем данные о СуперЦенах',
  'percent' => 20,
];
$CurDB->update('sites_agents', $addArray, $arWhere);

$fullCount = count($taskSection);
$onePercent = 80 / $fullCount;
$newproc = 20;


foreach ($taskSection as $item) {
    clearLogFile();
    logMessage("Начало обновления разделов элементов для раздела {$item}");
    sendElements($elementsList);


    $newproc = $newproc + $onePercent;
    $addArray = [
      'status_text' => 'Обработка данных',
      'percent' => round($newproc,2),
    ];
    $CurDB->update('sites_agents', $addArray, $arWhere);

    logMessage("Завершение обновления разделов элементов для раздела {$item}");
    sleep(20);
}


$timeEnd = date('Y.m.d G:i:s');
$addArray = [
  'status' => 'COMPLETED',
  'status_text' => 'Обмен завршен',
  'percent' => 100,
  'time_end' => $timeEnd,
];
$CurDB->update('sites_agents', $addArray, $arWhere);
