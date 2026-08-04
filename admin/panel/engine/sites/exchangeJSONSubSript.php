<?php

set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '2012M');
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/error_log.txt');

$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempus.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
die;
require($DOCUMENT_ROOT. "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');

$logFile = __DIR__ . '/exchange_log.txt';

function logMessage($message) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

function addSection($data) {
    $data = json_decode($data, true);

    $bs = new CIBlockSection;
    $arFields = [
        "ACTIVE" => "Y",
        "IBLOCK_ID" => 12,
        "NAME" => $data['NAME'],
        "CODE" => $data['CODE'],
        "DESCRIPTION" => $data['DESCRIPTION'],
        "PICTURE" => CFile::MakeFileArray($data['PICTURE']['SRC']),
        "DETAIL_PICTURE" => CFile::MakeFileArray($data['DETAIL_PICTURE']['SRC']),
        "UF_TAGS" => $data['UF_TAGS'],
        "UF_HELP_TEXT" => $data['UF_HELP_TEXT'],
        "IPROPERTY_TEMPLATES" => [
            "SECTION_META_TITLE" => $data['META_TITLE'],
            "SECTION_META_DESCRIPTION" => $data['META_DESCRIPTION'],
            "SECTION_META_KEYWORDS" => $data['META_KEYWORDS'],
        ]
    ];

    $parentSectionId = $data['PARENT_ID'];

    $parentSection = CIBlockSection::GetList(
        [],
        ['IBLOCK_ID' => 12, 'UF_HELP_TEXT' => $parentSectionId],
        false,
        ['ID']
    )->Fetch();

    if ($parentSection) {
        $parentSectionId = $parentSection['ID'];
    } else {
        $parentSectionId = 14;
    }

    $arFields["IBLOCK_SECTION_ID"] = $parentSectionId;

    $existingSection = CIBlockSection::GetList(
        [],
        ['IBLOCK_ID' => 12, 'UF_TAGS' => $data['UF_TAGS'], 'UF_HELP_TEXT' => $data['UF_HELP_TEXT']],
        false,
        ['ID']
    )->Fetch();

    if ($existingSection) {
        $sectionId = $existingSection['ID'];
        $bs->Update($sectionId, $arFields);
        return "Раздел обновлен с ID: " . $sectionId;
    } else {
        $sectionId = $bs->Add($arFields);
        if ($sectionId) {
            return "Раздел успешно добавлен с ID: " . $sectionId;
        } else {
            return "Ошибка при добавлении раздела: " . $bs->LAST_ERROR;
        }
    }
}

function addElements($data) {
    $data = json_decode($data, true);
    $el = new CIBlockElement;
    $results = [];

    foreach ($data as $elementData) {
        // Поиск существующего элемента по артикулу
        $existingElement = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => 12, 'PROPERTY_CML2_ARTICLE' => $elementData['PROPERTIES']['CML2_ARTICLE']],
            false,
            ['nTopCount' => 1],
            ['ID', 'ACTIVE']
        )->Fetch();

        $section = CIBlockSection::GetList(
            [],
            ['IBLOCK_ID' => 12, 'UF_HELP_TEXT' => $elementData['SECTION_ID']],
            false,
            ['ID']
        )->Fetch();

        $sectionId = $section ? $section['ID'] : false;

        $quantity = isset($elementData['QUANTITY']) ? floatval($elementData['QUANTITY']) : 0;
        $isActive = ($quantity > 0) ? 'Y' : 'N';

        $sectionIds = [];
        foreach ($elementData['SECTIONS'] as $oldSectionId) {
            $section = CIBlockSection::GetList(
                [],
                ['IBLOCK_ID' => 12, 'UF_HELP_TEXT' => $oldSectionId],
                false,
                ['ID']
            )->Fetch();
            if ($section) {
                $sectionIds[] = $section['ID'];
            }
        }

        $arFields = [
            "IBLOCK_ID" => 12,
            "IBLOCK_SECTION" => $sectionIds,
            "NAME" => $elementData['NAME'],
            "CODE" => $elementData['CODE'],
            "SORT" => $elementData['SHOW_COUNTER'],
            "PREVIEW_TEXT" => $elementData['PREVIEW_TEXT'],
            "PREVIEW_TEXT_TYPE" => "html",
            "DETAIL_TEXT" => $elementData['DETAIL_TEXT'],
            "DETAIL_TEXT_TYPE" => "html",
            "PREVIEW_PICTURE" => CFile::MakeFileArray($elementData['PREVIEW_PICTURE']['SRC']),
            "DETAIL_PICTURE" => CFile::MakeFileArray($elementData['DETAIL_PICTURE']['SRC']),
            "ACTIVE" => "Y",
        ];

        $arProps = [];

        foreach ($elementData['PROPERTIES'] as $code => $value) {
            $propInfo = CIBlockProperty::GetList([], ['IBLOCK_ID' => 12, 'CODE' => $code])->Fetch();

            if ($propInfo) {
                // Добавляем проверку на пустой массив
                if (is_array($value) && array_filter($value) === array()) {
                    $arProps[$code] = "";
                } else {
                    switch ($propInfo['PROPERTY_TYPE']) {
                        case 'L':
                            if (is_array($value)) {
                                $arProps[$code] = [];
                                foreach ($value as $listValue) {
                                    $enumValue = CIBlockPropertyEnum::GetList(
                                        [],
                                        ['IBLOCK_ID' => 12, 'PROPERTY_ID' => $propInfo['ID'], 'VALUE' => $listValue]
                                    )->Fetch();
                                    if ($enumValue) {
                                        $arProps[$code][] = $enumValue['ID'];
                                    }
                                }
                            } else {
                                $enumValue = CIBlockPropertyEnum::GetList(
                                    [],
                                    ['IBLOCK_ID' => 12, 'PROPERTY_ID' => $propInfo['ID'], 'VALUE' => $value]
                                )->Fetch();
                                if ($enumValue) {
                                    $arProps[$code] = $enumValue['ID'];
                                }
                            }
                            break;
                        case 'E':
                            if (is_array($value)) {
                                $arProps[$code] = [];
                                foreach ($value as $elementName) {
                                    $element = CIBlockElement::GetList(
                                        [],
                                        ['IBLOCK_ID' => $propInfo['LINK_IBLOCK_ID'], 'NAME' => $elementName],
                                        false,
                                        ['nTopCount' => 1],
                                        ['ID']
                                    )->Fetch();
                                    if ($element) {
                                        $arProps[$code][] = $element['ID'];
                                    }
                                }
                            } else {
                                $element = CIBlockElement::GetList(
                                    [],
                                    ['IBLOCK_ID' => $propInfo['LINK_IBLOCK_ID'], 'NAME' => $value],
                                    false,
                                    ['nTopCount' => 1],
                                    ['ID']
                                )->Fetch();
                                if ($element) {
                                    $arProps[$code] = $element['ID'];
                                }
                            }
                            break;
                        default:
                            $arProps[$code] = $value;
                    }
                }
            }
        }

        if (!empty($elementData['PROPERTIES']['MORE_PHOTO'])) {
            $morePhotoValues = [];
            foreach ($elementData['PROPERTIES']['MORE_PHOTO'] as $photo) {
                $morePhotoValues[] = CFile::MakeFileArray($photo['SRC']);
            }
            $arProps['MORE_PHOTO'] = $morePhotoValues;
        }

        $existingElement = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => 12, 'PROPERTY_CML2_ARTICLE' => $elementData['PROPERTIES']['CML2_ARTICLE']],
            false,
            ['nTopCount' => 1],
            ['ID']
        )->Fetch();

        if ($existingElement) {
            $elementId = $existingElement['ID'];
            if ($el->Update($elementId, $arFields)) {
                CIBlockElement::SetPropertyValuesEx($elementId, 12, $arProps);

                // Обновляем значение сортировки
                CIBlockElement::SetPropertyValuesEx($elementId, 12, array("SORT" => $elementData['SHOW_COUNTER']));

                $action = $isActive == 'Y' ? "обновлен" : "деактивирован";
                $results[] = "Элемент успешно {$action} с ID: " . $elementId;
                //logMessage("Элемент успешно {$action} с ID: " . $elementId);

                // Убедимся, что активность установлена правильно
                $el->Update($elementId, array("ACTIVE" => "Y"));
            } else {
                $results[] = "Ошибка при обновлении элемента: " . $el->LAST_ERROR;
                //logMessage("Ошибка при обновлении элемента: " . $el->LAST_ERROR);
            }
        } else {
            // Код создания нового элемента
            $arFields = [
                "IBLOCK_ID" => 12,
                "IBLOCK_SECTION" => $sectionIds,
                "NAME" => $elementData['NAME'],
                "CODE" => $elementData['CODE'],
                "SORT" => $elementData['SHOW_COUNTER'],
                "PREVIEW_TEXT" => $elementData['PREVIEW_TEXT'],
                "PREVIEW_TEXT_TYPE" => "html",
                "DETAIL_TEXT" => $elementData['DETAIL_TEXT'],
                "DETAIL_TEXT_TYPE" => "html",
                "PREVIEW_PICTURE" => CFile::MakeFileArray($elementData['PREVIEW_PICTURE']['SRC']),
                "DETAIL_PICTURE" => CFile::MakeFileArray($elementData['DETAIL_PICTURE']['SRC']),
                "ACTIVE" => "Y",
            ];

            $elementId = $el->Add($arFields, false, false, true);
            if ($elementId) {
                CIBlockElement::SetPropertyValuesEx($elementId, 12, $arProps);
                CIBlockElement::SetPropertyValuesEx($elementId, 12, array("SORT" => $elementData['SHOW_COUNTER']));
                $results[] = "Новый элемент успешно добавлен с ID: " . $elementId;
                //logMessage("Новый элемент успешно добавлен с ID: " . $elementId);

                // Добавляем цену и количество для нового элемента
                if (!empty($elementData['BASE_PRICE'])) {
                    CPrice::SetBasePrice($elementId, $elementData['BASE_PRICE'], 'RUB');
                }

                CCatalogProduct::Add(['ID' => $elementId, 'QUANTITY' => $quantity]);
            } else {
                $results[] = "Ошибка при добавлении нового элемента: " . $el->LAST_ERROR;
                //logMessage("Ошибка при добавлении нового элемента: " . $el->LAST_ERROR);
            }
        }

        if (!empty($elementData['BASE_PRICE'])) {
            CPrice::SetBasePrice($elementId, $elementData['BASE_PRICE'], 'RUB');
        }

        if (CCatalogProduct::GetByID($elementId)) {
            CCatalogProduct::Update($elementId, ['QUANTITY' => $quantity]);
        } else {
            CCatalogProduct::Add(['ID' => $elementId, 'QUANTITY' => $quantity]);
        }
    }

    return implode("\n", $results);
}

//new
if (!empty($argv[1])) {
  $dataArray = json_decode(file_get_contents($argv[1]), true);
  if ($argv[2] == 'addSection') {
      file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog_Sub.txt", print_r('###', true).PHP_EOL, FILE_APPEND);
      file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog_Sub.txt", print_r(date('d.m.Y H:i:s'), true).PHP_EOL, FILE_APPEND);
      file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog_Sub.txt", print_r('PROC: addSection', true).PHP_EOL, FILE_APPEND);
      file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog_Sub.txt", print_r($argv[2], true).PHP_EOL, FILE_APPEND);
      addSection($dataArray);
  } elseif ($argv[2] == 'addElements') {
      file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog_Sub.txt", print_r('###', true).PHP_EOL, FILE_APPEND);
      file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog_Sub.txt", print_r(date('d.m.Y H:i:s'), true).PHP_EOL, FILE_APPEND);
      file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog_Sub.txt", print_r('PROC: addElements', true).PHP_EOL, FILE_APPEND);
      file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog_Sub.txt", print_r($argv[2], true).PHP_EOL, FILE_APPEND);
      addElements($dataArray);
  } else {
      echo "Неизвестное действие";
  }
}
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/workFlow.txt", print_r('END', true).PHP_EOL, FILE_APPEND);
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/workFlow.txt", print_r(date('d.m.Y H:i:s'), true).PHP_EOL, FILE_APPEND);
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/workFlow.txt", print_r('###', true).PHP_EOL, FILE_APPEND);
//old
// if ($_POST['action'] == 'addSection') {
//     echo addSection($_POST['data']);
// } elseif ($_POST['action'] == 'addElements') {
//     echo addElements($_POST['data']);
// } else {
//     echo "Неизвестное действие";
// }
