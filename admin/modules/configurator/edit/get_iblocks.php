<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

$res = CIBlock::GetList(
    Array(),
    Array(
        'TYPE' => 'aspro_max_content',
        'ACTIVE' => 'Y',
        "CNT_ACTIVE" => 'Y',
    ),
    true
);

while ($arRes = $res->Fetch()) {
    echo '<option value="' . $arRes['ID'] . '">' . $arRes['NAME'] . '</option>';
}
