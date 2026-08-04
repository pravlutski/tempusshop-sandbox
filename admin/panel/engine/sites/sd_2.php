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

$timeStart = date('Y.m.d G:i:s');
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog_2.txt", print_r('START:'.$timeStart, true).PHP_EOL, FILE_APPEND);

if (!CModule::IncludeModule("iblock") || !CModule::IncludeModule("catalog")) {
    die("Не удалось подключить необходимые модули");
}

$iblockId = 16;

$totalCount = CIBlockElement::GetList(
    array(),
    array(
        "IBLOCK_ID" => $iblockId,
        "ACTIVE" => "Y",
    ),
    array()
);

$arSelect = array(
    "ID",
    "IBLOCK_ID",
    "NAME",
    "ACTIVE",
    "PROPERTY_CML2_ARTICLE",
    "PROPERTY_AVAILABILITY_BY",
    "PROPERTY_AVAILABILITY_RU"
);

$arFilter = array(
    "IBLOCK_ID" => $iblockId,
    "ACTIVE" => "Y",
);

$res = CIBlockElement::GetList(
    array("SORT" => "ASC"),
    $arFilter,
    false,
    false,
    $arSelect
);

$elements = array();

$basePriceTypeId = 1;
$baseBelPriceTypeId = 2;

while ($ob = $res->GetNextElement()) {
    $arFields = $ob->GetFields();
    $arProps = $ob->GetProperties();

    $prices = CPrice::GetList(
        array(),
        array(
            "PRODUCT_ID" => $arFields["ID"],
            "CATALOG_GROUP_ID" => array($basePriceTypeId, $baseBelPriceTypeId)
        )
    );

    $elementPrices = array();
    while ($price = $prices->Fetch()) {
        $elementPrices[$price["CATALOG_GROUP_ID"]] = $price["PRICE"];
    }

    $productInfo = CCatalogProduct::GetByID($arFields["ID"]);
    $quantity = $productInfo["QUANTITY"];

    $elements[] = array(
        "ID" => $arFields["ID"],
        "NAME" => $arFields["NAME"],
        "ACTIVE" => $arFields["ACTIVE"],
        "ARTICLE" => $arProps["CML2_ARTICLE"]["VALUE"],
        "BASE_PRICE" => $elementPrices[$basePriceTypeId] ?? 0,
        "BASE_BEL_PRICE" => $elementPrices[$baseBelPriceTypeId] ?? 0,
        "QUANTITY" => $quantity,
        "AVAILABILITY_BY" => $arProps["AVAILABILITY_BY"]["VALUE"] ?? null,
        "AVAILABILITY_RU" => $arProps["AVAILABILITY_RU"]["VALUE"] ?? null
    );
}

file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/volume_2.txt", print_r($elements, true).PHP_EOL, FILE_APPEND);

$timeEnd = date('Y.m.d G:i:s');
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/tmpLog_2.txt", print_r('END:'.$timeEnd, true).PHP_EOL, FILE_APPEND);
