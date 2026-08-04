<?

$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
die;
const NO_KEEP_STATISTIC = true;
const NOT_CHECK_PERMISSIONS = true;
const BASE_URL = "https://tempusshop.ru";

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '12000M');

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;

Loader::includeModule('iblock');
Loader::includeModule('panel.manager');

$logFile = __DIR__ . '/exchange_log.txt';
global $timeStart;
$timeStart = date('Y.m.d G:i:s');

$CurDB = new DBPanel();

$result = $CurDB->Query("SELECT * FROM sites_exchange_hash");
$rows = $CurDB->fetchAll($result);
$hashArray = [];
foreach ($rows as $row) {
  	$hashArray[$row['bitrix_id']] = $row['hash'];
}

$result = $CurDB->Query("SELECT * FROM sites_exchange_hash_section");
$rows = $CurDB->fetchAll($result);
$hashArray = [];
foreach ($rows as $row) {
  	$hashArraySec[$row['bitrix_id']] = $row['hash'];
}

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

function sendSections($parentSectionId = 932) {
    $sections = SectionTable::getList([
        'filter' => ['IBLOCK_ID' => 16, 'IBLOCK_SECTION_ID' => $parentSectionId],
        'select' => ['ID', 'NAME', 'CODE', 'DESCRIPTION', 'PICTURE', 'DETAIL_PICTURE', 'UF_*'],
    ])->fetchAll();

    foreach ($sections as $section) {
        $sectionDataArray = '';
        $sectionData = [
            'NAME' => $section['NAME'],
            'CODE' => $section['CODE'],
            'PARENT_ID' => $parentSectionId,
            'DESCRIPTION' => $section['DESCRIPTION'],
            'PICTURE' => CFile::GetFileArray($section['PICTURE']),
            'DETAIL_PICTURE' => CFile::GetFileArray($section['DETAIL_PICTURE']),
            'UF_TAGS' => $section['NAME'],
            'UF_HELP_TEXT' => $section['ID'],
            'UF_ALT_NAME' => $section['UF_ALT_NAME'],
            'META_TITLE' => $section['UF_META_TITLE'],
            'META_DESCRIPTION' => $section['UF_META_DESCRIPTION'],
            'META_KEYWORDS' => $section['UF_META_KEYWORDS'],
        ];

        $CurDB = new DBPanel();

        $hash = md5(serialize($sectionData));
        if (!isset($hashArraySec[$section['ID']])) {

            $arInsert = [
              [
                'bitrix_id' => $section['ID'] ,
                'hash' => $hash
              ],
            ];

            $CurDB->insert('sites_exchange_hash_section', $arInsert);
            file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/products/'.$timeStart.'.log','РАЗДЕЛ '. $section['NAME'] . ' - ХЭШ ОТСУТСТВУЕТ В БД, РАЗДЕЛ ДОБАВЛЕН В ОБМЕН' . PHP_EOL, FILE_APPEND);
            $sectionDataArray = $sectionData;
        }else if ($hash != $hashArray[$section['ID']]) {
            $arWhereHash[] = [
              'column' => 'bitrix_id',
              'operator' => '=',
              'value' => $section['ID']
            ];
            $CurDB->update('sites_exchange_hash_section', ['hash' => $hash], $arWhereHash);
            $sectionDataArray = $sectionData;
            file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/products/'.$timeStart.'.log','РАЗДЕЛ '. $section['NAME'] . ' - ХЭШ ИЗМЕНИЛСЯ, РАЗДЕЛ ДОБАВЛЕН В ОБМЕН' . PHP_EOL, FILE_APPEND);
            unset($arWhereHash);
        } else {
            file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/products/'.$timeStart.'.log','РАЗДЕЛ '. $section['NAME'] . ' - ХЭШ НЕ  ИЗМЕНИЛСЯ, РАЗДЕЛ НЕ НУЖДАЕТСЯ В ОБНОВЛЕНИИ' . PHP_EOL, FILE_APPEND);
        }


        if (!empty($sectionDataArray)) {
            global $timeStart;
            $response = sendRequestWithRetry('https://tempus.ru/local/rest/exchange.php', [
                'action' => 'addSection',
                'logname' => $timeStart,
                'data' => json_encode($sectionDataArray)
            ]);

            logMessage("Отправлен раздел {$section['ID']}: " . $response);
       }

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
function sendElements($sectionId, $page = 1, $pageSize = 50, $processedSections = [], $hashArray): void
{

    // if (in_array($sectionId, $processedSections)) {
    //   return;
    // }
    $processedSections[] = $sectionId;



    $filter = [
      'IBLOCK_ID' => 16,
      'SECTION_ID' => $sectionId,
      'PROPERTY_OZON_ACTIVE_VALUE' => 'Да',
      //"ACTIVE" => 'Y'
    ];

    $select = [
      'ID',
      'NAME',
      'CODE',
      'PREVIEW_TEXT',
      'DETAIL_TEXT',
      'SECTION_ID',
      'SORT',
      'PREVIEW_PICTURE',
      'DETAIL_PICTURE',
      'SORT',
    ];

    $dbElements = \CIBlockElement::GetList(
      ['DATE_CREATE' => 'DESC'],
      $filter,
      false,
      [
          'nPageSize' => $pageSize,
          'iNumPage' => $page,
      ],
      $select
    );

    $elements = [];
    while ($element = $dbElements->GetNext()) {
      $elements[] = $element;
    }

    $elementDataArray = [];

    foreach ($elements as $element) {
//        $availability = CIBlockElement::GetProperty(16, $element['ID'], [], ['CODE' => 'AVAILABILITY_RU'])->Fetch();
        $elementData = [
            'NAME' => $element['NAME'],
            'CODE' => $element['CODE'],
            'XML_ID' => $element['ID'],
            'SORT' => $element['SORT'],
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

            $resols = explode('.',$detailPictureArray['FILE_NAME']);
            if ($resols[1] == 'png') {
              $detailPictureArray['CONTENT_TYPE'] = 'image/png';
            }

            $elementData['DETAIL_PICTURE'] = $detailPictureArray;

        }
        //исключитьб
        $excludeProperties = [
            'PROPERTY_WBPRICE',
            'WBPRICE',
            'PROPERTY_OZSB_PRICE',
            'OZSB_PRICE',
            'PROPERTY_NAME_MARKETPLACE',
            'NAME_MARKETPLACE',
            'PROPERTY_IMAGE_MARKETPLACE',
            'IMAGE_MARKETPLACE',
            'PROPERTY_TYPEOFSKLAD',
            'TYPEOFSKLAD',
            'PROPERTY_DATE_LAST_STOCK',
            'DATE_LAST_STOCK',
            'PROPERTY_NAME_WB_MP',
            'NAME_WB_MP',
            'PROPERTY_INFO_WB_IMAGE',
            'NAME_INFO_WB_IMAGE',
            'PROPERTY_INFOOZON_IMAGE',
            'NAME_INFOOZON_IMAGE',
            'PROPERTY_PRICE_OZKZ',
            'PRICE_OZKZ',
            'PROPERTY_ACTIVE_YA',
            'ACTIVE_YA',
            'PROPERTY_NAME_YA_MP',
            'NAME_YA_MP',
            'PROPERTY_EX_YA',
            'EX_YA',
            'DC_SALE',
            'DP_DISCOUNT',
            'PROPERTY_INFOGRAPH_BASE',
            'NAME_INFOGRAPH_BASE',
            'PROPERTY_SBER_PRICE',
            'SBER_PRICE',
            'PROPERTY_PRICE_KZ',
            'PRICE_KZ',
            'PROPERTY_MARKETPLACE_WB_TAGS',
            'MARKETPLACE_WB_TAGS',
        ];
        // Обработка свойств
        $props = CIBlockElement::GetProperty(16, $element['ID']);
        while ($prop = $props->Fetch()) {
            if (in_array($prop['CODE'], $excludeProperties)) {
                continue;
            }

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

        // Обработка INSTRUCTIONS
        $instFiles = [];
        $instFilesTmp = CIBlockElement::GetProperty(16, $element['ID'], array(), array('CODE' => 'INSTRUCTIONS'));
        while ($instFileTmp = $instFilesTmp->Fetch()) {
            $instFileArray = CFile::GetFileArray($instFileTmp['VALUE']);
            if ($instFileArray) {
                $instFileArray['SRC'] = BASE_URL . $instFileArray['SRC'];
                $instFiles[] = $instFileArray;
            }
        }
        $elementData['PROPERTIES']['INSTRUCTIONS'] = $instFiles;

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



        $CurDB = new DBPanel();

        $hash = md5(serialize($elementData));
        if (!isset($hashArray[$element['ID']])) {

            $arInsert = [
              [
                'bitrix_id' => $element['ID'] ,
                'hash' => $hash
              ],
            ];

            $CurDB->insert('sites_exchange_hash', $arInsert);

            $elementDataArray[] = $elementData;
            file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/products/'.$timeStart.'.log', $elementData['PROPERTIES']['CML2_ARTICLE'] . ' - ХЭШ ОТСУТСТВУЕТ В БД, ТОВАР ДОБАВЛЕН В ОБМЕН' . PHP_EOL, FILE_APPEND);
        } else if ($hash != $hashArray[$element['ID']]) {
          $arWhereHash[] = [
            'column' => 'bitrix_id',
            'operator' => '=',
            'value' => $element['ID']
          ];
          $CurDB->update('sites_exchange_hash', ['hash' => $hash], $arWhereHash);
          $elementDataArray[] = $elementData;
          unset($arWhereHash);
          file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/products/'.$timeStart.'.log', $elementData['PROPERTIES']['CML2_ARTICLE'] . ' - ХЭШ ИЗМЕНИЛСЯ, ТОВАР ДОБАВЛЕН В ОБМЕН' . PHP_EOL, FILE_APPEND);
        } else {
          file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/products/'.$timeStart.'.log', $elementData['PROPERTIES']['CML2_ARTICLE'] . ' - ХЭШ НЕ ИЗМЕНИЛСЯ, ТОВАР ПРОПУЩЕН' . PHP_EOL, FILE_APPEND);
        }
        /* отладка */
        // logMessage(print_r($elementData, true));
        if ($quantity <= 0) {
            logMessage("Элемент {$element['ID']} деактивирован (нулевой или отрицательный остаток)");
        }
    }

    if (!empty($elementDataArray)) {
        global $timeStart;

        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/exchange_debug.log', print_r($elementDataArray,true). PHP_EOL, FILE_APPEND);
        $response = sendRequestWithRetry('https://tempus.ru/local/rest/exchange.php', [
            'action' => 'addElements',
            'logname' => $timeStart,
            'data' => json_encode($elementDataArray)
        ]);
        logMessage("Отправлено " . count($elementDataArray) . " элементов: " . $response);
        unset($elementDataArray);
          // Отправляем следующую страницу элементов
        if (count($elements) >= $pageSize) {
            sendElements($sectionId, $page + 1, $pageSize, $processedSections,$hashArray);
        }
    }

    // Обработка подразделов
    $subsections = SectionTable::getList([
        'filter' => ['IBLOCK_ID' => 16, 'IBLOCK_SECTION_ID' => $sectionId],
        'select' => ['ID']
    ])->fetchAll();

    foreach ($subsections as $subsection) {
        sendElements($subsection['ID'],1,50,array(),$hashArray);
    }
}

function sendElementsByID($ids): void
{
    $elements = ElementTable::getList([
        'filter' => [
            'IBLOCK_ID' => 16,
            'ID' => $ids,
        ],
        'order' => ['DATE_CREATE' => 'DESC'],
        'select' => ['ID', 'NAME', 'CODE', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'],
        'limit' => 1000,
        'offset' => 0
    ])->fetchAll();

    $elementDataArray = [];

    foreach ($elements as $element) {

        $elementData = [
            'NAME' => $element['NAME'],
            'CODE' => $element['CODE'],
            'XML_ID' => $element['ID'],
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
            $resols = explode('.',$detailPictureArray['FILE_NAME']);
            if ($resols[1] == 'png') {
              $detailPictureArray['CONTENT_TYPE'] = 'image/png';
            }

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

        $viewCount = CIBlockElement::GetByID($element['ID']);
        if($arView = $viewCount->GetNext()){
            $elementData['SHOW_COUNTER'] = $arView["SHOW_COUNTER"];
        }

        $elementDataArray[] = $elementData;
    }

    if (!empty($elementDataArray)) {
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/exchange_debug.log', print_r($elementDataArray,true). PHP_EOL, FILE_APPEND);
        $response = sendRequestWithRetry('https://tempus.ru/local/rest/exchange.php', [
            'action' => 'addElements',
            'data' => json_encode($elementDataArray)
        ]);
        print_r($elementData);
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
	sendElementsByID($ids);
	die;
}


// sendElementsByID(array(71042));
// echo 'ok';
// die();

// $taskSection = [
//   611,
//   1690
// ];
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
//     1690,
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
            'ACTIVE' => 'Y'
        ],
        false,
        ['ID']
    );

    while ($sectionL2 = $resL2->GetNext()) {
        $taskSection[] = $sectionL2['ID'];
    }
}


//wdhs
$CurDB = new DBPanel();
$arWhere[] = [
  'column' => 'code',
  'operator' => '=',
  'value' => 'Exchange'
];


$addArray = [
  'status' => 'PROCESS',
  'status_text' => 'Инициирован обмен',
  'percent' => 0,
  'time_start' => $timeStart,
];
$CurDB->update('sites_agents', $addArray, $arWhere);

file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/products/'.$timeStart.'.log', $timeStart . ' - Инициализирован обмен' . PHP_EOL, FILE_APPEND);
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

    sendElements($item,1,50,array(),$hashArray);

    $newproc = $newproc + $onePercent;
    $addArray = [
      'status_text' => 'Обмен товарами по разделу ' . $item,
      'percent' => round($newproc,2),
    ];
    $CurDB->update('sites_agents', $addArray, $arWhere);

    $message = "Завершение обмена данными по разделу {$item}";
}
$addArray = [
'status_text' => 'Обмен категориями',
'percent' => 98,
];
$CurDB->update('sites_agents', $addArray, $arWhere);

sendSections();
$timeEnd = date('Y.m.d G:i:s');
$addArray = [
  'status' => 'COMPLETED',
  'status_text' => 'Обмен завршен',
  'percent' => 100,
  'time_end' => $timeEnd,
];
$CurDB->update('sites_agents', $addArray, $arWhere);
//sendSections();
file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/products/'.$timeStart.'.log', $timeEnd . ' - Обмен завершен' . PHP_EOL, FILE_APPEND);
logMessage("Обмен данными завершен");
