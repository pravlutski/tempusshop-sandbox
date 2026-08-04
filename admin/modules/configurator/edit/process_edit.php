<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tag_id = $_POST["tag_id"];
    $tag_name = $_POST["tag_name"];
    $selected_properties = isset($_POST["selected_properties"]) ? $_POST["selected_properties"] : [];
    $property_values = $_POST["property_value"];
    $sort_order = $_POST["sort_order"];

    // Преобразуем выбранные элементы в ID перед сохранением
    $selected_elements = $_POST["selected_elements"];
    $new_selected_elements = [];

    if (!empty($selected_elements)) {
        foreach ($selected_elements as $element) {
            $element_id = getElementIdByName($element); // Функция для получения ID элемента по его названию
            if ($element_id) {
                $new_selected_elements[] = $element_id;
            }
        }
    }

    $jsonElementData = json_encode($new_selected_elements);

    if($selected_properties) {
        $jsonPropertyData = json_encode(array_map(null, $selected_properties, $property_values));
    } else {
        $jsonPropertyData = '{}';
    }

    $selected_sections = $_POST["selected_sections"];
    $jsonSectionData = json_encode($selected_sections);

    global $DB;

    $strSql = "UPDATE `ci_configurator_tags`
               SET `tag_name` = '" . $DB->ForSql($tag_name) . "',
                   `properties_json` = '" . $DB->ForSql($jsonPropertyData, "5120") . "',
                   `sections_json` = '" . $DB->ForSql($jsonSectionData, "5120") . "',
                   `elements_json` = '" . $DB->ForSql($jsonElementData, "5120") . "',
                   `updated_at` = now()
               WHERE `id` = " . intval($tag_id);

    echo $strSql;

    $result = $DB->Query($strSql);
    if ($result !== false) {
        echo "Тег успешно обновлен";
        header("Location: https://tempusshop.ru/admin/modules/configurator/");
        exit();
    } else {
        echo "Ошибка при обновлении тега";
    }
} else {
    echo "Данные формы не были переданы";
}

/**
 * @param $elementName
 * @return bool
 */
function getElementIdByName($elementName): bool
{
    $iblockId = 16; // ID информационного блока, в котором находятся элементы
    $arFilter = [
        "IBLOCK_ID" => $iblockId,
        "NAME" => $elementName
    ];

    $res = CIBlockElement::GetList([], $arFilter, false, false, ['ID']);
    if ($ob = $res->GetNext()) {
        return $ob['ID'];
    } else {
        return false;
    }
}
