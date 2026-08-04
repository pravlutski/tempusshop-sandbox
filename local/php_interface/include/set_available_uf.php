<?
require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

$bs = new CIBlockSection;

// выборка разделов 2 уровня
$filter = [
    'IBLOCK_ID' => CProSet::IB_CATALOG,
    'ACTIVE' => 'Y',
    'GLOBAL_ACTIVE' => 'Y',
    'ACTIVE_DATE' => 'Y',
    '=DEPTH_LEVEL' => 2,
    "SECTION_ID" => 932,
    ">ELEMENT_CNT" => 0
];
$select = [
    'ID',
];

$db = CIBlockSection::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'], $filter, false, $select, false);

$sectionsId = [];
while($info = $db->GetNext()) {
    $sectionsId[] = $info['ID'];
}

// обнуляем UF_ значения для разделов 2-ого уровня
foreach ($sectionsId as $sectionId) {
    $fields = Array(
        "UF_IS_AVAILABLE_RU" => '',
        "UF_IS_AVAILABLE_BY" => '',
    );
    $bs->Update($sectionId, $fields);
}

// получаем элементы каталога по полученным id разделов 2-ого уровня и их подразделах
$filter = [
    'IBLOCK_ID' => CProSet::IB_CATALOG,
    'SECTION_ID' => $sectionsId,
    'ACTIVE' => 'Y',
    '!PROPERTY_AVAILABILITY_RU' => false,
    '!PROPERTY_AVAILABILITY_BY' => false,
    'INCLUDE_SUBSECTIONS' => 'Y'
];
$select = [
    'ID',
    'NAME',
    'PROPERTY_AVAILABILITY_RU',
    'PROPERTY_AVAILABILITY_BY',
    'IBLOCK_SECTION_ID',
];

// собираем id непустых (по свойствам доступности элементов) разделов для RU и BY
$availableSectionsIdRU = $availableSectionsIdBY = $sectionsId = [];
$db = CIBlockElement::GetList([], $filter, false, false, $select);
while ($info = $db->GetNextElement()) {
    $fields = $info->GetFields();
    //PROPERTY_AVAILABILITY_RU_ENUM_ID 514 - value id Для недоступных в RU
    //PROPERTY_AVAILABILITY_BY_ENUM_ID 494 - value id Для недоступных в BY
    if ($fields['PROPERTY_AVAILABILITY_RU_ENUM_ID'] != 514) {
        $availableSectionsIdRU[] = $fields['IBLOCK_SECTION_ID'];
    }
    if ($fields['PROPERTY_AVAILABILITY_BY_ENUM_ID'] != 494) {
        $availableSectionsIdBY[] = $fields['IBLOCK_SECTION_ID'];
    }
    $sectionsId[] = $fields['IBLOCK_SECTION_ID'];
}

$availableSectionsIdRU = array_unique($availableSectionsIdRU);
$availableSectionsIdBY = array_unique($availableSectionsIdBY);
$sectionsId = array_unique($sectionsId);

// устанавливаем UF_ значения для раздеов 3 уровня
foreach ($sectionsId as $secId) {
    $fields = Array(
        "UF_IS_AVAILABLE_RU" => (in_array($secId, $availableSectionsIdRU)) ? 'Y' : '',
        "UF_IS_AVAILABLE_BY" => (in_array($secId, $availableSectionsIdBY)) ? 'Y' : '',
    );
    $bs->Update($secId, $fields);
}

// получаем доступные разделы 2-ого уровня по id дочерних разделов из шага выше
$filter = [
    'IBLOCK_ID' => CProSet::IB_CATALOG,
    'ACTIVE' => 'Y',
    'ID' => array_merge($availableSectionsIdRU, $availableSectionsIdBY),
];
$select = [
    'ID',
    'IBLOCK_SECTION_ID'
];

$availableSectionsId2LvlRU = $availableSectionsId2LvlBY = $availableSectionsId2Lvl = [];
$db = CIBlockSection::GetList([], $filter, false, $select, false);
while($info = $db->GetNext()) {
    if (empty($info['IBLOCK_SECTION_ID'])) {
        continue;
    }
    $availableSectionsId2Lvl[] = $info['IBLOCK_SECTION_ID'];

    if (in_array($info['ID'], $availableSectionsIdRU)) {
        $availableSectionsId2LvlRU[] = $info['IBLOCK_SECTION_ID'];
    }
    if (in_array($info['ID'], $availableSectionsIdBY)) {
        $availableSectionsId2LvlBY[] = $info['IBLOCK_SECTION_ID'];
    }
}
$availableSectionsId2LvlRU = array_unique($availableSectionsId2LvlRU);
$availableSectionsId2LvlBY = array_unique($availableSectionsId2LvlBY);
$availableSectionsId2Lvl = array_unique($availableSectionsId2Lvl);


// устанавливаем значения UF_ для разделов 2-ого уровня
foreach ($availableSectionsId2Lvl as $availableSectionId2Lvl) {
    $fields = Array(
        "UF_IS_AVAILABLE_RU" => (in_array($availableSectionId2Lvl, $availableSectionsId2LvlRU)) ? 'Y' : '',
        "UF_IS_AVAILABLE_BY" => (in_array($availableSectionId2Lvl, $availableSectionsId2LvlBY)) ? 'Y' : '',
    );
    $bs->Update($availableSectionId2Lvl, $fields);
}