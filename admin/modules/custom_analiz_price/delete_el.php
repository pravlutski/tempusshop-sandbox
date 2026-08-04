<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

global $DB;

$elements = $_POST['elementsId'];

foreach ($elements as $el):
    $strSql = "DELETE FROM `ci_custom_analiz_price` WHERE id = {$el}";

    $result = $DB->Query($strSql);
endforeach;

header("Location: https://tempusshop.ru/admin/modules/configurator/");

exit;