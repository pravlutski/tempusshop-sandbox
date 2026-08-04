<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

if (isset($_POST['propertyId'])) {
    $propertyId = $_POST['propertyId'];

    $property_enums = CIBlockPropertyEnum::GetList(Array("SORT" => "ASC"), Array("IBLOCK_ID" => 16, "CODE" => $propertyId));
    $values = [];
    while ($enum_fields = $property_enums->GetNext()) {
        $values[] = ['ID' => $enum_fields['ID'], 'VALUE' => $enum_fields['VALUE']];
    }
    echo json_encode($values);
}

exit;
