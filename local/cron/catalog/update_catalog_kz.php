<?php

$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

const NO_KEEP_STATISTIC = true;
const NOT_CHECK_PERMISSIONS = true;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

global $DB;

$strSql = "SELECT * FROM `ci_price` WHERE `active_kz` = 'Y'";
$tags = $DB->Query($strSql, false);

while ($row = $tags->Fetch()):

    $arFilter = array_merge(array(
        "IBLOCK_ID" => 16,
        "ID" => $row["bitrix_id"],
        "INCLUDE_SUBSECTIONS" => "Y",
    ), []);

    $arFilter["PRODUCT.PRICE_BASE"] = "Y";

    $res = CIBlockElement::GetList(array(), $arFilter);

    while ($ob = $res->GetNextElement()) {
        $arFields = $ob->GetFields();
        echo $arFields["ID"] . "<br>";
        echo $arFields["NAME"] . "<br>";
        CIBlockElement::SetPropertyValuesEx($arFields["ID"], 16, array('AVAILABILITY_KZ' => 1951));
    }
endwhile;
