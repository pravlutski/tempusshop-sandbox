<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tags_list = $_POST["tags_list"];
    $resource = $_POST["resource"];

    $multipleTagCreator = new MultipleTagCreator($tags_list, $resource);
    $multipleTagCreator->createMultipleTags();
} else {
    echo "Данные формы не были переданы";
}

class MultipleTagCreator
{
    private $tags_list;
    private $active = "Y";
    private $sort_order = 0;
    private $resource;

    public function __construct($tags_list, $resource)
    {
        $this->tags_list = $tags_list;
        $this->resource = $resource;
    }

    public function createMultipleTags(): void
    {
        $tagsArray = explode("\n", $this->tags_list);

        global $DB;

        foreach ($tagsArray as $tag) {
            // Разбиваем строку по символу ";"
            $tag_parts = explode(";", $tag, 2); // Ограничение разбиения до 2 элементов
            $tag_name = trim($tag_parts[0]); // Название тега

            // Если вторая часть присутствует и является числом, устанавливаем sort_order
            if(isset($tag_parts[1]) && is_numeric($tag_parts[1])) {
                $this->sort_order = (int)$tag_parts[1];
            }

            // Проверка наличия тега в базе данных
            $checkDuplicateQuery = "SELECT `tag_name` FROM `ci_configurator_tags` WHERE `tag_name` = '" . $DB->ForSql($tag_name) . "'";
            $checkDuplicateResult = $DB->Query($checkDuplicateQuery);

            if ($checkDuplicateResult->Fetch()) {
                echo "Тег с названием: " . $tag_name . " уже существует. Пропускаем.<br>";
                continue;
            }

            $strSql = "INSERT INTO `ci_configurator_tags` (`tag_name`, `resource`, `active`, `sort_order`, `created_at`, `updated_at`)
           VALUES ('" . $DB->ForSql($tag_name) . "', '" . $DB->ForSql($this->resource) . "',  '" . $this->active . "', " . $this->sort_order . ", now(), now())";

            echo $strSql . "<br>";
            $result = $DB->Query($strSql);
            if ($result === false) {
                echo "Ошибка при создании тега: " . $tag_name . "<br>";
            }

            $this->sort_order = 500; // Сброс значения sort_order для следующей итерации
        }

        echo "Теги успешно обновлены";
        header("Location: https://tempusshop.ru/admin/modules/configurator/");
    }
}