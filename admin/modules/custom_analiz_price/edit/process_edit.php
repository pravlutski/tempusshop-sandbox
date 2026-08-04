<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST["id"];
    $article = $_POST["article"];
    $markup = $_POST["markup"];

    global $DB;

    $strSql = "UPDATE `ci_custom_analiz_price`
               SET `article` = '" . $DB->ForSql($article) . "',
                   `markup` = '" . $DB->ForSql($markup) . "',
                   `updated_at` = now()
               WHERE `id` = " . intval($id);

    echo $strSql;

    $result = $DB->Query($strSql);
    if ($result !== false) {
        echo "Профиль успешно обновлен";
        header("Location: https://tempusshop.ru/admin/modules/custom_analiz_price/");
        exit();
    } else {
        echo "Ошибка при обновлении профиля";
    }
} else {
    echo "Данные формы не были переданы";
}
