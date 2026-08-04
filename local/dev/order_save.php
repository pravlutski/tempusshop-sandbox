<?php

require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/main/include/prolog_before.php');

$order = \Bitrix\Sale\Order::load(430139); // 123 – ID заказа

$propertyCollection = $order->getPropertyCollection();
$properties = $propertyCollection->getArray();

foreach ($properties as $property) {
    if ($property['CODE'] == "LAST_NAME") {
        $property["VALUE"] = "Новая фамилия";
        $propertyCollection->setValuesFromPost([$property['ID'] => $property]);
    }
}

$r = $order->save();
if (!$r->isSuccess())
{
    var_dump($r->getErrorMessages());
}
