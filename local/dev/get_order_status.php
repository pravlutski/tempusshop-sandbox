<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Sale;

Loader::includeModule('sale');
Loader::includeModule('main');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $orderOldSiteId = $_POST['order_id'];

    // Поиск заказа по пользовательскому свойству ID_ORDER_OLD_SITE
    $orderList = Sale\Order::getList([
        'filter' => ['ID' => $orderOldSiteId],
        'select' => ['ID', 'STATUS_ID']
    ]);

    if ($order = $orderList->fetch()) {
        $response = [
            'status' => $order['STATUS_ID']
        ];
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Order not found.'
        ];
    }
} else {
    $response = [
        'status' => 'error',
        'message' => 'Error ero'
    ];
}

// Установка заголовка Content-Type и вывод ответа в формате JSON
header('Content-Type: application/json');

echo json_encode($response);
