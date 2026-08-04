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
use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Bitrix\Main\Type\DateTime;

Loader::includeModule('sale');

define('MOYSKLAD_API_URL', 'https://api.moysklad.ru/api/remap/1.2');
define('MOYSKLAD_API_LOGIN', 'bitrix@tempusint');
define('MOYSKLAD_API_PASSWORD', 'akkxbTO88yQR');
define('ORDERS_TO_SYNC', 1000);

function getNewBitrixOrders() {

    $CurDB = new DBPanel();

    GLOBAL $DB;

    $arOrderNumbers = ["113547","126090","128550","153862","160078","166639","169138","199663","228971","567747","567755","567779","567803","567829","567845","567876","567908","567917","567926","567961","567970","567977","568025","568050","568081","568084","568117","568126","568168","568177","568188","568207","568233","568258","568263","568267","568278","568294","568355","568356","568390","568398","568399","568401","568410","568413","568439","568466","568474","568479","568561","568603","568651","568652","568681","568695","568719","568724","568725","568729","568731","568755","568765","568787","568812","568863","568883","568907","568909","568914","568961","568963","568968","569031","569054","569075","569107","569113","569164","569195","569325","569326","569345","569406","569427","569459","569505","569507","569575","569588","569618","569643","569679","569707","569804","569805","569821","569841","569858","569863","569864","569875","569897","569974","569985","570021","570030","570043","570044","570070","570094","570098","570102","570127","570128","570131","570169","570178","570190","570196","570236","570266","570280","572444","572445","572446","572477","572482","572495","572550","572578","572602","572608","572615","572618","572655","572687","572688","572691","572701","572705","572717","572776","572777","572778","572780","572781","572785","572793","572804","572805","572807","572808","572809","572810","572811","572812","572813","572815","572816","572817","572818","572819","572820","572823","572824","572825","572826","572827","572829","572831","572833","572834","572837","572838","572841","572842","572843","572844","572851","572852","572853","572855","572856","572857","572859","572860","572861","572862","572863","572864","572865","572866","572867","572869","572870","572871","572872","572874","572875","572876","572877","572880","572881","572882","572883","572884","572899","572907","572908","572914","572917","572953","572967","572968","572971","572972","572992","573000","573001","573011","573018","573020","573027","573045","573058","573059","573123","573126","573140","573145","573178","573179","573180","573181","573197","573223","573248","573275","573320","573323","573334","573347","573353","573360","573362","573363","573368","573395","573404","573405","573435","573465","573489","573507","573513","573514","573517","573527","573552","573560","573604","573626","573643","573652","573658","573660","573673","573674","573679","573680","573688","573689","573703","573730","573734","573761","573786","573797","573799","573803","573839","573848","573888","573915","573925","573926","573949","573954","573969","573970","573995","574001","574002","574026","574040","574056","574080","574106","574108","574112","574162","574178","574179","574200","574227","574243","574249","574263","574269","574359","574376","574377","574381","581225","589613","589622","2758148","117099C"];
    $uniqueOrderNumbers = array_unique($arOrderNumbers);

  foreach ($uniqueOrderNumbers as $accountNumber) {
      $order = Order::loadByAccountNumber($accountNumber);
      if ($order !== false) {  // Check if order was found
          $ordersBD[$order->getField('ID')] = $order;
      } else {
          // Optionally log or handle cases where order wasn't found
          error_log("Order not found for account number: " . $accountNumber);
      }
  }

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

function findProduct($id) {
    GLOBAL $DB;
    $strSql = "SELECT MS_ID FROM ci_ms_assortment WHERE BX_ID = '{$id}' AND SITE_ID = 's1'";
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
      'CO' => '99fa66b1-3d56-11f0-0a80-1687005d92cd', // Готов к доставке
      'RD' => 'b74f47bb-0adb-11ea-0a80-0042000ab6da', // Возврат после вручения
    ];

    $counterparty = findCounterparty($bitrixOrder['USER_ID']);
    if (empty($counterparty)) {
      $counterparty = createCounterparty($bitrixOrder['USER_ID']);
    }

    $positions = [];
    foreach ($products as $product) {

        $msProduct = findProduct($product['PRODUCT_ID']);

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

    // print_r($orderData);

    $newId = $ms->createOrder($orderData);

    if (!isset($newId['errors'])) {
      $response = $newId;
    }

    return $response;
}

function updateMoySkladOrder($bitrixOrder, $products, $msId) {

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
      'CO' => '99fa66b1-3d56-11f0-0a80-1687005d92cd', // Готов к доставке
      'RD' => 'b74f47bb-0adb-11ea-0a80-0042000ab6da', // Возврат после вручения
    ];

    $positions = [];
    // print_r($products);
    foreach ($products as $product) {

        $msProduct = findProduct($product['PRODUCT_ID']);

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

    $orderData = [
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
    // print_r($orderData);
    $newId = $ms->updateOrder($orderData, $msId);

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

        //new\

        $ms_id = findOrder($order['ACCOUNT_NUMBER']);

        if (!empty($ms_id)) {
          echo "Найден в мс ОБВНОЛЯЕМ <br>";
          $msOrder = updateMoySkladOrder($order, $products, $ms_id);
        } else {
          echo "Не найден в мс СОЗДАЕМ <br>";
          $msOrder = createMoySkladOrder($order, $products, $msUserSettings);
        }

        if (!empty($msOrder)) {
          echo "Заказ #{$order['ID']} успешно создан/обновлен в Мой Склад (ID: {$msOrder['id']})<br>";
          $sql = "UPDATE ms_orders SET action = 'DONE', status = '0' WHERE bitrix_id = '{$order['ID']}'";
          $CurDB->query($sql);
        } else {
          echo "Ошибка при создании/обновлении заказа #{$order['ID']} <br>";
        }
        //old
        // if ($order["DB"]['action'] == 'CREATE') {
        //   echo "Создаем заказ #{$order['ID']}.<br>";
        //   $msOrder = createMoySkladOrder($order, $products, $msUserSettings);
        //
        //   if(isset($msOrder['id'])) {
        //     $sql = "UPDATE ms_orders SET ms_id = '{$ms_id}' WHERE bitrix_id = '{$order['ID']}'";
        //   }
        // }
        //
        // if ($order["DB"]['action'] == 'UPDATE') {
        //   echo "Обновляем заказ #{$order['ID']}.<br>";
        //   if (empty($order["DB"]['ms_id'])) {
        //     echo "Отсутствует MS_ID ищем заказ в MS.<br>";
        //     $ms_id = findOrder($order['ACCOUNT_NUMBER']);
        //     if (empty($ms_id)) {continue; echo "Заказ отсутствует в MS.<br>";}
        //     $order["DB"]['ms_id'] = $ms_id;
        //     $sql = "UPDATE ms_orders SET ms_id = '{$ms_id}' WHERE bitrix_id = '{$order['ID']}'";
        //     $CurDB->query($sql);
        //     echo "Получен MS_ID заказа #{$order['ID']}.<br>";
        //   }
        //   $msOrder = updateMoySkladOrder($order, $products);
        // }
        //
        // if (!empty($msOrder)) {
        //   echo "Заказ #{$order['ID']} успешно создан/обновлен в Мой Склад (ID: {$msOrder['id']})<br>";
        //   $sql = "UPDATE ms_orders SET action = 'DONE', status = '0' WHERE bitrix_id = '{$order['ID']}'";
        //   $CurDB->query($sql);
        // } else {
        //   echo "Ошибка при создании/обновлении заказа #{$order['ID']} <br>";
        // }
    }

    echo "Синхронизация завершена. Обработано заказов: " . count($orders) . "<br>";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
    \Bitrix\Main\Diag\Debug::writeToFile($e->getMessage(), "Ошибка синхронизации", "moysklad_integration.log");
    exit(1);
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
