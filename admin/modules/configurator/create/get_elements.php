<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['iblockId'])) {
    $iblockId = intval($_POST['iblockId']);
    $selectedElementId = isset($_POST['selectedElementId']) ? intval($_POST['selectedElementId']) : 0;

    $arSelect = Array("ID", "NAME", "DATE_ACTIVE_FROM");
    $arFilter = Array("IBLOCK_ID" => $iblockId, "ACTIVE_DATE" => "Y", "ACTIVE" => "Y");

    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize" => 50), $arSelect);

    echo '<select name="property_value[]" class="form-control">';
    while ($ob = $res->GetNextElement()) {
        $arFields = $ob->GetFields();
        $selected = $selectedElementId == $arFields['ID'] ? 'selected' : '';
        echo '<option value="' . $arFields['ID'] . '" ' . $selected . '>' . $arFields['NAME'] . '</option>';
    }
    echo '</select>';
}
