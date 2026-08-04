<?php

require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/main/include/prolog_before.php');

$parameters = [
    'filter' => [
        "USER_ID" => [
            135989,
            161898
        ],
    ],
    'order' => ["DATE_INSERT" => "ASC"]
];
$dbRes = \Bitrix\Sale\Order::getList($parameters);
while ($order = $dbRes->fetch())
{
    var_dump($order);
}
