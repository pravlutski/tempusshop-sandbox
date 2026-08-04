<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");
CModule::IncludeModule('panel.manager');
CModule::IncludeModule('panel.manager');

define('MOYSKLAD_API_URL', 'https://api.moysklad.ru/api/remap/1.2');
define('MOYSKLAD_API_LOGIN', 'bitrix@tempusint');
define('MOYSKLAD_API_PASSWORD', 'akkxbTO88yQR');
define('ORDERS_TO_SYNC', 1000);

function getNewBitrixOrders() {

    $CurDB = new DBPanel();

    GLOBAL $DB;

    $result = $CurDB->query("SELECT * FROM ms_orders WHERE bitrix_id = '687878'");
    // $result = $CurDB->query("SELECT * FROM ms_orders WHERE status = '1'");
		$rows = $CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$ordersBD[$row['bitrix_id']] = $row;
		}
		unset($result);
		unset($rows);

    $filter = [
        'filter' => [
            'ID' => array_keys($ordersBD)
            // 'ID' => [683207]
        ],
        'select' => ['ID', 'DATE_INSERT', 'STATUS_ID', 'PRICE', 'CURRENCY', 'USER_DESCRIPTION', 'USER_ID','ACCOUNT_NUMBER'],
        'order' => ['ID' => 'ASC'],
        'limit' => ORDERS_TO_SYNC
    ];

    $dbOrders = \Bitrix\Sale\Order::getList($filter);
    $orders = [];

    while ($order = $dbOrders->fetch()) {
        $orderObj = \Bitrix\Sale\Order::load($order['ID']);

        $order['DELIVERY_PRICE'] = $orderObj->getDeliveryPrice();

        $order['DB'] = $ordersBD[$order['ID']];
        $orders[] = $order;
    }

    return $orders;
}

function getOrderProducts($orderId) {
    $order = \Bitrix\Sale\Order::load($orderId);
    $basket = $order->getBasket();
    $items = [];

    foreach ($basket as $basketItem) {
        $items[] = [
            'PRODUCT_ID' => $basketItem->getProductId(),
            'NAME' => $basketItem->getField('NAME'),
            'QUANTITY' => $basketItem->getQuantity(),
            'PRICE' => $basketItem->getPrice(),
            'CURRENCY' => $basketItem->getCurrency(),
            'PRODUCT_XML_ID' => $basketItem->getField('PRODUCT_XML_ID') // Артикул товара
        ];
    }

    return $items;
}

function findCounterparty($userId) {
    $ms = new MoyskladAPI('s1');

    $user = \Bitrix\Main\UserTable::getById($userId)->fetch();

    $name = $user['LAST_NAME'] . ' ' . $user['NAME'] . ' ' . $user['SECOND_NAME'];
    // $name = str_replace(" ", "%20", trim($name));
    $name = urlencode(trim($name));

    $agent = $ms->searchAgent($name);


    if (!empty($agent)) {
      $response = $agent[0]['meta'];
    } else {
      $response = false;
    }
    return $response;
}

function createCounterparty($userId) {

    $ms = new MoyskladAPI('s1');

    $user = \Bitrix\Main\UserTable::getById($userId)->fetch();

    $name = $user['LAST_NAME'] . ' ' . $user['NAME'] . ' ' . $user['SECOND_NAME'];
    // $name = urlencode(trim($name));
    // if (empty($user['PERSONAL_PHONE'])) {
    //   $user['PERSONAL_PHONE'] = '+799999999';
    // }
    $userData = [
      'name' => $name,
      'phone' => $user['PERSONAL_PHONE'],
      'email' => $user['EMAIL'],
    ];


    $newCounter = $ms->createCounterparty($userData);

    if (!empty($newCounter)) {
      $response = $newCounter['meta'];
    } else {
      $response = false;
    }
    return $response;
}

function findOrder($orderId) {

    $ms = new MoyskladAPI('s1');

    $order = $ms->searchOrder($orderId);

    if (!empty($order)) {
      $response = $order[0]['id'];
    } else {
      $response = false;
    }
    return $response;
}

function findProduct($xml_id) {
    GLOBAL $DB;
    $strSql = "SELECT MS_ID FROM ci_ms_assortment WHERE XML_ID = '{$xml_id}' AND SITE_ID = 's1'";
		$results = $DB->query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
      $msId = $row['MS_ID'];
    }

    if (!empty($msId)) {
        $data['meta'] = [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/product/{$msId}",
          "type" => "product",
          "mediaType" => "application/json"
        ];

        return $data;
    }

    return false;
}

function createMoySkladOrder($bitrixOrder, $products, $msUserSettings) {
    $msDefault = $msUserSettings;

    $ms = new MoyskladAPI('s1');

    $orderStatuses = [
      'N' => '2ff6b54f-3c86-11f0-0a80-1a9700374081', // Новый
      'P' => '30ab4ad4-3c86-11f0-0a80-1a9700374123', // Прибыл в пункт выдачи
      'SE' => '312a0c7b-3c86-11f0-0a80-1a9700374185', // Готов к доставке
      'CL' => '31df1886-3c86-11f0-0a80-1a97003741b0', // Сборка
      'no' => '26ebfd74-3c87-11f0-0a80-1320007a4cda', // Передумал/Ошибка
      'F' => '2862e3f9-3c87-11f0-0a80-1320007a4e5c', // Выполнен
      'OS' => 'd4fb40b4-3cca-11f0-0a80-1410000d480c', // Нет в наличии
      'rp' => 'e0d3105a-3cca-11f0-0a80-1410000d5658', // Возврат прибыл в ПВЗ
      'TA' => '03d1483d-3d53-11f0-0a80-1325003afbf7', // Самовывоз
      'CO' => '03d1483d-3d53-11f0-0a80-1325003afbf7', // Готов к доставке
      'RD' => 'b74f47bb-0adb-11ea-0a80-0042000ab6da', // Возврат после вручения
    ];

    $counterparty = findCounterparty($bitrixOrder['USER_ID']);
    if (empty($counterparty)) {
      $counterparty = createCounterparty($bitrixOrder['USER_ID']);
    }

    $positions = [];
    foreach ($products as $product) {

        $msProduct = findProduct($product['PRODUCT_XML_ID']);

        if (!$msProduct) {
            continue;
        }

        $positions[] = [
            'quantity' => (float)$product['QUANTITY'],
            'price' => (float)$product['PRICE'] * 100,
            'assortment' => [
                'meta' => $msProduct['meta']
            ]
        ];

        if (!empty($bitrixOrder['DELIVERY_PRICE'])) {
          $positions[] = [
              'quantity' => 1,
              'price' => (float)$bitrixOrder['DELIVERY_PRICE'] * 100,
              'assortment' => [
                  'meta' => [
                    "href" => "https://api.moysklad.ru/api/remap/1.2/entity/service/7fe63217-0b1c-11ea-0a80-03b100002fa0",
                    "type" => "service",
                    "mediaType" => "application/json"
                  ]
              ]
          ];
        }
    }


    $orderData = [
        'name' => $bitrixOrder['ACCOUNT_NUMBER'],
        'organization' => $msDefault['defaultCompany'],
        'store' => $msDefault['defaultPlace'],
        'agent' => [
            'meta' => [
              "href" => $counterparty['href'],
              "type" => "counterparty",
              "mediaType" => "application/json"
            ]
        ],
        'positions' => $positions,
        'description' => ''
    ];
    if ( !empty($orderStatuses[ $bitrixOrder["STATUS_ID"] ])  ){
      $orderData['state'] = [
        'meta' => [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/{$orderStatuses[$bitrixOrder["STATUS_ID"]]}",
          "type" => "state",
          "mediaType" => "application/json"
        ]
      ];
    }

    print_r($orderData);

    $newId = $ms->createOrder($orderData);

    if (!isset($newId['errors'])) {
      $response = $newId;
    }

    return $response;
}

function updateMoySkladOrder($bitrixOrder, $products) {

    $ms = new MoyskladAPI('s1');

    $orderStatuses = [
      'N' => '2ff6b54f-3c86-11f0-0a80-1a9700374081', // Новый
      'P' => '30ab4ad4-3c86-11f0-0a80-1a9700374123', // Прибыл в пункт выдачи
      'SE' => '312a0c7b-3c86-11f0-0a80-1a9700374185', // Готов к доставке
      'CL' => '31df1886-3c86-11f0-0a80-1a97003741b0', // Сборка
      'no' => '26ebfd74-3c87-11f0-0a80-1320007a4cda', // Передумал/Ошибка
      'F' => '2862e3f9-3c87-11f0-0a80-1320007a4e5c', // Выполнен
      'OS' => 'd4fb40b4-3cca-11f0-0a80-1410000d480c', // Нет в наличии
      'rp' => 'e0d3105a-3cca-11f0-0a80-1410000d5658', // Возврат прибыл в ПВЗ
      'TA' => '03d1483d-3d53-11f0-0a80-1325003afbf7', // Самовывоз
      'CO' => '03d1483d-3d53-11f0-0a80-1325003afbf7', // Готов к доставке
      'RD' => 'b74f47bb-0adb-11ea-0a80-0042000ab6da', // Возврат после вручения
    ];

    $positions = [];
    foreach ($products as $product) {

        $msProduct = findProduct($product['PRODUCT_XML_ID']);

        if (!$msProduct) {
            continue;
        }

        $positions[] = [
            'quantity' => (float)$product['QUANTITY'],
            'price' => (float)$product['PRICE'] * 100,
            'assortment' => [
                'meta' => $msProduct['meta']
            ]
        ];
    }


    $orderData = [
        'name' => $bitrixOrder['ACCOUNT_NUMBER'],
        'positions' => $positions,
    ];

    if ( !empty($orderStatuses[ $bitrixOrder["STATUS_ID"] ])  ){
      $orderData['state'] = [
        'meta' => [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/{$orderStatuses[$bitrixOrder["STATUS_ID"]]}",
          "type" => "state",
          "mediaType" => "application/json"
        ]
      ];
    }

    print_r($orderData);
    $newId = $ms->updateOrder($orderData,$bitrixOrder['DB']['ms_id']);

    if (!isset($newId['errors'])) {
      $response = $newId;
    }

    return $response;
}

try {
    $CurDB = new DBPanel();
    $ms = new MoyskladAPI('s1');

    $orders = getNewBitrixOrders();

    if (empty($orders)) {
        echo "Нет новых заказов для синхронизации.";
        exit;
    }

    $msUserSettings  = $ms->getDefault();

    if (empty($msUserSettings["defaultCompany"])) {
        echo "Не заданы настройки для компании по умолчанию";
        exit;
    }

    if (empty($msUserSettings["defaultPlace"])) {
        echo "Не заданы настройки для склада по умолчанию";
        exit;
    }


    foreach ($orders as $order) {
        echo "Обработка заказа #{$order['ID']}...<br>";
        $msOrder = '';
        $products = getOrderProducts($order['ID']);


        if (empty($products)) {
            echo "В заказе #{$order['ID']} нет товаров, пропускаем.<br>";
            continue;
        }

        if ($order["DB"]['action'] == 'CREATE') {
          echo "Создаем заказ #{$order['ID']}.<br>";
          $msOrder = createMoySkladOrder($order, $products, $msUserSettings);

          if(isset($msOrder['id'])) {
            $sql = "UPDATE ms_orders SET ms_id = '{$ms_id}' WHERE bitrix_id = '{$order['ID']}'";
          }
        }

        if ($order["DB"]['action'] == 'UPDATE') {
          echo "Обновляем заказ #{$order['ID']}.<br>";
          if (empty($order["DB"]['ms_id'])) {
            echo "Отсутствует MS_ID ищем заказ в MS.<br>";
            $ms_id = findOrder($order['ACCOUNT_NUMBER']);
            if (empty($ms_id)) {continue; echo "Заказ отсутствует в MS.<br>";}
            $order["DB"]['ms_id'] = $ms_id;
            $sql = "UPDATE ms_orders SET ms_id = '{$ms_id}' WHERE bitrix_id = '{$order['ID']}'";
            $CurDB->query($sql);
            echo "Получен MS_ID заказа #{$order['ID']}.<br>";
          }
          $msOrder = updateMoySkladOrder($order, $products);
        }

        if (!empty($msOrder)) {
          echo "Заказ #{$order['ID']} успешно создан/обновлен в Мой Склад (ID: {$msOrder['id']})<br>";
          $sql = "UPDATE ms_orders SET action = 'DONE', status = '0' WHERE bitrix_id = '{$order['ID']}'";
          $CurDB->query($sql);
        } else {
          echo "Ошибка при создании/обновлении заказа #{$order['ID']} <br>";
        }
    }

    echo "Синхронизация завершена. Обработано заказов: " . count($orders) . "<br>";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
    \Bitrix\Main\Diag\Debug::writeToFile($e->getMessage(), "Ошибка синхронизации", "moysklad_integration.log");
    exit(1);
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
