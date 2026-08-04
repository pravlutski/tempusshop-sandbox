<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
echo 111;

Loader::includeModule('sale');
Loader::includeModule('main');
Loader::includeModule('catalog');

$elements = ElementTable::getList([
    'filter' => [
        'IBLOCK_ID' => 16,
        'ID' => 1002
    ],
    'select' => ['ID', 'NAME', 'IBLOCK_SECTION_ID'],
])->fetchAll();

foreach ($elements as $element) {

    print_r($element);
}