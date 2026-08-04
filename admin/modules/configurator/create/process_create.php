<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

class TagCreator {
    private $tag_name;
    private $selected_properties;
    private $property_values;
    private $active = "Y";
    private $sort_order;
    private $resource;

    /**
     * @param $tag_name
     * @param $selected_properties
     * @param $property_values
     * @param $sort_order
     */
    public function __construct($tag_name, $selected_properties, $property_values, $sort_order, $resource) {
        $this->tag_name = $tag_name;
        $this->selected_properties = $selected_properties;
        $this->property_values = $property_values;
        $this->sort_order = $sort_order;
        $this->resource = $resource;
    }

    /**
     * @return void
     */
    public function createTag(): void
    {
        $propertyData = array_map(null, $this->selected_properties, $this->property_values);
        $jsonPropertyData = json_encode($propertyData);

        $selected_sections = $_POST["selected_sections"];
        $jsonSectionData = json_encode($selected_sections);

        global $DB;

        $strSql = "INSERT INTO `ci_configurator_tags` (`tag_name`, `resource`, `properties_json`, `sections_json`, `active`, `sort_order`, `created_at`, `updated_at`)
                   VALUES ('" . $DB->ForSql($this->tag_name) . "', '" . $DB->ForSql($this->resource) . "', '" . $DB->ForSql($jsonPropertyData, "5120") . "', '" . $DB->ForSql($jsonSectionData, "5120") . "', '" . $this->active . "', " . $this->sort_order . ", now(), now())";

        $result = $DB->Query($strSql);
        if ($result !== false) {
            echo "Тег успешно создан";
            header("Location: https://tempusshop.ru/admin/modules/configurator/");
            exit();
        } else {
            echo "Ошибка при создании тега";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tag_name = $_POST["tag_name"];
    $selected_properties = $_POST["selected_properties"];
    $property_values = $_POST["property_value"];
    $sort_order = $_POST["sort_order"];
    $resource = $_POST["resource"];

    $tagCreator = new TagCreator($tag_name, $selected_properties, $property_values, $sort_order, $resource);
    $tagCreator->createTag();
} else {
    echo "Данные формы не были переданы";
}
