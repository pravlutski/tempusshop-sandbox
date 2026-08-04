<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
die;
use Bitrix\Main\Application;
use Bitrix\Catalog\ProductTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Bitrix\Sale;
use Bitrix\Sale\Fuser;
use Bitrix\Main\Context;
use Bitrix\Main\Diag\Debug;

Loader::includeModule('sale');
Loader::includeModule('main');
Loader::includeModule('iblock');
Loader::includeModule('catalog');
//wdhs
Loader::includeModule('panel.manager');
$CurDB = new DBPanel();
$arWhere[] = [
  'column' => 'code',
  'operator' => '=',
  'value' => 'OrdersBy'
];

function updateOrderProductsXml($orderId)
{
    global $DB;

    if (!$orderId || !is_numeric($orderId)) {
        return "Некорректный ID заказа.";
    }

    $connection = Application::getConnection();
    $sqlHelper = $connection->getSqlHelper();

    // Запрос в таблицу b_sale_basket с фильтрацией по ORDER_ID
    $basketItems = $connection->query("
        SELECT ID, PRODUCT_ID, CATALOG_XML_ID, PRODUCT_XML_ID
        FROM b_sale_basket
        WHERE ORDER_ID = " . $sqlHelper->forSql($orderId)
    );

    $catalogXmlId = 'aspro_mshop_catalog_s1';
    $updatedItems = 0;

    while ($item = $basketItems->fetch()) {
        $updateFields = [];

        // Проверяем поле CATALOG_XML_ID
        if (empty($item['CATALOG_XML_ID'])) {
            $updateFields['CATALOG_XML_ID'] = $catalogXmlId;
        }

        // Проверяем поле PRODUCT_XML_ID
        if (empty($item['PRODUCT_XML_ID'])) {
            // Получаем XML_ID товара по его PRODUCT_ID из инфоблока 16
            $product = ElementTable::getList([
                'select' => ['XML_ID'],
                'filter' => ['ID' => $item['PRODUCT_ID'], 'IBLOCK_ID' => 16]
            ])->fetch();

            if ($product && !empty($product['XML_ID'])) {
                $updateFields['PRODUCT_XML_ID'] = $product['XML_ID'];
            }
        }

        // Если есть поля для обновления, выполняем UPDATE запроса
        if (!empty($updateFields)) {
            $updateQuery = "UPDATE b_sale_basket SET ";

            $updateParts = [];
            if (!empty($updateFields['CATALOG_XML_ID'])) {
                $updateParts[] = "CATALOG_XML_ID = '" . $sqlHelper->forSql($updateFields['CATALOG_XML_ID']) . "'";
            }
            if (!empty($updateFields['PRODUCT_XML_ID'])) {
                $updateParts[] = "PRODUCT_XML_ID = '" . $sqlHelper->forSql($updateFields['PRODUCT_XML_ID']) . "'";
            }

            $updateQuery .= implode(', ', $updateParts);
            $updateQuery .= " WHERE ID = " . intval($item['ID']);

            $connection->queryExecute($updateQuery);
            $updatedItems++;
        }
    }

    return "Обновлено записей: " . $updatedItems;
}

function findOrCreateUser($userData) {
    // Проверка на существующего пользователя по EMAIL или PERSONAL_PHONE
    if($userData['EMAIL']){
        $filter = [
            'LOGIC' => 'OR',
            ['=EMAIL' => $userData['EMAIL']],
            ['=PERSONAL_PHONE' => $userData['PERSONAL_PHONE']]
        ];
        $user = UserTable::getList(['filter' => $filter])->fetch();
    } else {
        $filter = [
            'LOGIC' => 'OR',
            ['=EMAIL' => $userData['PERSONAL_PHONE'] . '_user@tempus.ru'],
            ['=PERSONAL_PHONE' => $userData['PERSONAL_PHONE']]
        ];
        $user = UserTable::getList(['filter' => $filter])->fetch();
    }

    if ($user) {
        // Если пользователь найден
        return $user['ID'];
    } else {
        // Если пользователь не найден, создаем нового
        $splitFIO = explode(' ', $userData['FIO']);
        $name = isset($splitFIO[1]) ? $splitFIO[1] : '';
        $lastName = isset($splitFIO[0]) ? $splitFIO[0] : '';
        $secondName = isset($splitFIO[2]) ? $splitFIO[2] : '';

        $randomPassword =randString(8);

        $userFields = [
            'NAME' => $name,
            'LAST_NAME' => $lastName,
            'SECOND_NAME' => $secondName,
            'EMAIL' => $userData['EMAIL'] ?: $userData['PERSONAL_PHONE'] . '_user@tempus.ru',
            'PERSONAL_PHONE' => $userData['PERSONAL_PHONE'],
            'LOGIN' => $userData['EMAIL'] ?: $userData['PERSONAL_PHONE'] . '_user',
            'PASSWORD' => $randomPassword,
            'CONFIRM_PASSWORD' => $randomPassword,
        ];

        $user = new \CUser;
        $userId = $user->Add($userFields);
        if (intval($userId) > 0) {
            return $userId;
        } else {
            return false;
        }
    }
}

function createOrder($orderData) {
    $userId = findOrCreateUser($orderData['USER']);
    if (!$userId) {
        return false;
    }
    if ( empty($orderData['BASKET']) ) return false;
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/getdata.txt', print_r('step 1\n',true). PHP_EOL,FILE_APPEND);
    $siteId = "s2";
    $order = Sale\Order::create($siteId, $userId);

    $order->setPersonTypeId(1);
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/getdata.txt', print_r('step 2\n',true). PHP_EOL,FILE_APPEND);
    // $order->setField('CURRENCY', $orderData['CURRENCY']);
    $order->setField('CURRENCY', 'BYN');

    // Создаем корзину
    $basket = Sale\Basket::create($siteId);

    // Добавляем товары в корзину
    foreach ($orderData['BASKET'] as $item) {
        $productId = findProductByArticle($item['ARTICLE']);
        if (!$productId) {
            $productId = $item['PRODUCT_ID'];
        }
        if ($productId) {
            $basketItem = $basket->createItem('catalog', $productId);
            if (!$basketItem) {
                Debug::writeToFile("Product not added to basket: " . $productId, "Basket Error", "/local/dev/order_creation_errors.txt");
                continue;
            }

            // **Устанавливаем цены вручную**
            $basePrice = (float) $item['BASE_PRICE']; // Исходная цена без скидок
            $discountPrice = (float) $item['DISCOUNT_PRICE']; // Сумма скидки
            $finalPrice = (float) $item['PRICE']; // Цена после применения скидки

            // Проверяем корректность переданных цен
            if ($basePrice <= $finalPrice) {
                $discountPrice = 0; // Если скидка не передана или превышает, то сбрасываем
            }

            $basketFields = [
                'QUANTITY' => $item['QUANTITY'],
                'LID' => $siteId,
                'CURRENCY' => Bitrix\Currency\CurrencyManager::getBaseCurrency(),
                'PRICE' => round($finalPrice),  // Итоговая цена с учетом скидки
                'BASE_PRICE' => $basePrice, // Исходная цена без скидок
                'CUSTOM_PRICE' => 'Y', // Отключаем авто-пересчет цен системой
                'NAME' => $item['NAME'],
                'PRODUCT_PROVIDER_CLASS' => "CCatalogProductProvider",
            ];

            if ($discountPrice > 0) {
                $basketFields['DISCOUNT_PRICE'] = $discountPrice; // Применяем скидку, если она есть
            }

            $basketItem->setFields($basketFields);
        }
    }
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/getdata.txt', print_r('step 2\n',true). PHP_EOL,FILE_APPEND);
    if (!empty($orderData['STATUS_ID'])) {
        $statusId = mapStatusId($orderData['STATUS_ID']);
        $order->setField('STATUS_ID', $statusId);
    }
    // Устанавливаем корзину в заказ
    $order->setBasket($basket);

    // Добавляем доставку
    $shipmentCollection = $order->getShipmentCollection();
    $shipment = $shipmentCollection->createItem();
    $shipment->setField('CURRENCY', $orderData['CURRENCY']);
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/getdata.txt', print_r('step 3\n',true). PHP_EOL,FILE_APPEND);
    foreach ($orderData['SHIPMENT'] as $shipmentData) {
        $deliveryId = mapDeliveryId($shipmentData['DELIVERY_ID']);
        $shipment->setFields([
            'DELIVERY_ID' => $deliveryId,
            'PRICE_DELIVERY' => $orderData['DELIVERY_PRICE'],
            'BASE_PRICE_DELIVERY' => $orderData['DELIVERY_BASE_PRICE'],
        ]);
    }
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/getdata.txt', print_r('step 4\n',true). PHP_EOL,FILE_APPEND);
    // Добавляем оплату
    $paymentCollection = $order->getPaymentCollection();
    foreach ($orderData['PAYMENT'] as $paymentData) {
        $paySystemId = mapPaySystemId($paymentData['PAY_SYSTEM_ID']);
        $payment = $paymentCollection->createItem();
        $payment->setFields([
            'PAY_SYSTEM_ID' => $paySystemId,
            'PAY_SYSTEM_NAME' => $paymentData['PAY_SYSTEM_NAME'],
            'SUM' => $paymentData['SUM'],
        ]);

        // Если заказ уже был оплачен, помечаем это
        if ($orderData['SUM_PAID'] > 0 && $payment->getSum() <= $orderData['SUM_PAID']) {
            $payment->setPaid('Y');
        }
    }
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/getdata.txt', print_r('step 5\n',true). PHP_EOL,FILE_APPEND);
    // Добавляем пользовательские данные заказа
    // Save user fields
    if($siteId === "s2") {
        $userdescr = "tempus.by\nID заказа: {$orderData['ID']}\nКомментарий клиента: {$orderData['USER_DESCRIPTION']}\n";
    } else {
      $userdescr = "tempus.ru\nID заказа: {$orderData['ID']}\nКомментарий клиента: {$orderData['USER_DESCRIPTION']}\n";
      if (!empty($orderData["T_BANK_BILLIN_ID"])) {
        $userdescr .= "Номер транзакции T-Bank: {$orderData["T_BANK_BILLIN_ID"]}\n";
      }
      if (!empty($orderData["T_BANK_STATUS"])) {
        $userdescr .= "Статус оплаты T-Bank: {$orderData["T_BANK_STATUS"]}";
      }
    }

    $order->setField('USER_DESCRIPTION', $userdescr);
    $order->setField('COMMENTS', $orderData["MANAGER_COMMENT"]);
    // Устанавливаем пользовательские свойства заказа
    $propertyCollection = $order->getPropertyCollection();
    $propertyFields = [
        'id_order_tempusru' => $orderData['ID'],
        'FIO' => $orderData['USER']['FIO'],
        'EMAIL' => $orderData['USER']['EMAIL'],
        'PHONE' => $orderData['USER']['PERSONAL_PHONE'],
        'LOCATION' => $orderData['USER']['LOCATION'],
        'ADDRESS' => $orderData['USER']['ADDRESS'],
        'ZIP' => $orderData['USER']['ZIP'],
        'IPOLSDEK_CNTDTARIF' => $orderData['USER']['IPOLSDEK_CNTDTARIF']
    ];
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/getdata.txt', print_r('step 6\n',true). PHP_EOL,FILE_APPEND);
    foreach ($propertyFields as $code => $value) {
        $prop = $propertyCollection->getItemByOrderPropertyCode($code);
        if ($prop) {
            $prop->setValue($value);
        }
    }
    $order->setField('ACCOUNT_NUMBER', $orderData["ACCOUNT_NUMBER"]);
    // Завершаем оформление заказа
    $order->doFinalAction(true);
    $GLOBALS['DISABLE_TEMPUS_HANDLER'] = true;
    $result = $order->save();
    $ordTMP = Sale\Order::load( $order->getId() );
    $ordTMP->setField( 'ACCOUNT_NUMBER', $orderData['ACCOUNT_NUMBER'] );
    $ordTMP->save();
    unset($GLOBALS['DISABLE_TEMPUS_HANDLER']);
    if ($result->isSuccess()) {

        if ($order) {
              $newOrderId = $order->getId();
              $externalID = $order->getField('ACCOUNT_NUMBER');
              $tradeBindingCollection = $order->getTradeBindingCollection();

              $tpId = null;
              /** @var Bitrix\Sale\TradeBindingEntity $item */
              foreach ($tradeBindingCollection as $item) {
                  $tpId = $item->getField('TRADING_PLATFORM_ID');
                  break;
              }

              if (!$tpId) {
                  $res = \Bitrix\Sale\TradingPlatform\OrderTable::add(array(
                      "ORDER_ID" => $newOrderId,
                      "TRADING_PLATFORM_ID" => 13,
                      "EXTERNAL_ORDER_ID" => $externalID
                  ));

                  if (!$res->isSuccess()) {
                      $errors = $res->getErrorMessages();
                  }
              }
          }

        return $order->getId();
        $GLOBALS['DISABLE_TEMPUS_HANDLER'] = true;
        $order->save();
        unset($GLOBALS['DISABLE_TEMPUS_HANDLER']);
    } else {
        Debug::writeToFile($result->getErrorMessages(), "Order Creation Error", "/local/dev/order_creation_errors.txt");
        return false;
    }

}

function findProductByArticle($article) {
    if (empty($article)) {
        return false;
    }
    $res = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => 16, 'PROPERTY_CML2_ARTICLE' => $article],
        false,
        ['nTopCount' => 1],
        ['ID']
    )->Fetch();

    return $res["ID"];
}

function mapDeliveryId($oldDeliveryId) {
    $deliveryMap = [
        '9' => '1',
        '145' => '12',
        '143' => '80',
        '141' => '19',
        '140' => '25',
        '3' => '21',
        '2' => '22',
        '146' => '73',
    ];
    return isset($deliveryMap[$oldDeliveryId]) ? $deliveryMap[$oldDeliveryId] : '1';
}

function mapPaySystemId($oldPaySystemId) {
    $paySystemMap = [
        '3' => '1',
        '15' => '28',
        '14' => '35',
        '13' => '38',
        '12' => '47',
        '11' => '46',
        '7' => '34',
        '16' => '48',
        '22' => '27',
        '21' => '24',
        '17' => '42',
    ];
    return isset($paySystemMap[$oldPaySystemId]) ? $paySystemMap[$oldPaySystemId] : '1';
}

function findOrderByTempusId($tempusId) {
      $propertyCollection = \Bitrix\Sale\Internals\OrderPropsValueTable::getList([
         'select' => ['ORDER_ID'],
         'filter' => [
             'CODE' => 'id_order_tempusru',
             'VALUE' => $tempusId
         ],
         'limit' => 1
     ])->fetch();

     if ($propertyCollection && $propertyCollection['ORDER_ID']) {
         return \Bitrix\Sale\Order::load($propertyCollection['ORDER_ID']);
     }

    return false;
}

function updateOrder($order, $orderData) {

      $basket = $order->getBasket();
      $basketItems = $basket->getBasketItems();

      // 1. Убедимся, что FUSER_ID установлен
      if (!$basket->getFUserId()) {
        $fuserId = \Bitrix\Sale\Fuser::getId();
        $basket->setFUserId($fuserId);

        file_put_contents(
            '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_debug.log',
            date('Y-m-d H:i:s') . " - Установлен FUSER_ID: " . $fuserId . "\n",
            FILE_APPEND
        );
      }
      file_put_contents(
          '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_debug.log',
          date('Y-m-d H:i:s') . " - Начало обновления корзины заказа\n",
          FILE_APPEND
      );

      foreach ($basketItems as $item) {
          $deleteResult = $item->delete();
          if (!$deleteResult->isSuccess()) {
              file_put_contents(
                  '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_errors.log',
                  date('Y-m-d H:i:s') . " - Ошибка удаления товара ID: " . $item->getProductId() .
                  " - " . implode(", ", $deleteResult->getErrorMessages()) . "\n",
                  FILE_APPEND
              );
          }
      }

      // Логируем успешное удаление товаров
      file_put_contents(
          '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_debug.log',
          date('Y-m-d H:i:s') . " - Товары из корзины удалены\n",
          FILE_APPEND
      );

      foreach ($orderData['BASKET'] as $item) {
          try {

              file_put_contents(
                  '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_debug.log',
                  date('Y-m-d H:i:s') . " - Обрабатываем товар: " . print_r($item, true) . "\n",
                  FILE_APPEND
              );

              $productId = !empty($item['ARTICLE'])
                  ? findProductByArticle($item['ARTICLE'])
                  : $item['PRODUCT_ID'];

              if (!$productId) {
                  $errorMsg = "Не удалось определить ID товара для ARTICLE: {$item['ARTICLE']}, PRODUCT_ID: {$item['PRODUCT_ID']}";
                  file_put_contents(
                      '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_errors.log',
                      date('Y-m-d H:i:s') . " - " . $errorMsg . "\n",
                      FILE_APPEND
                  );
                  continue;
              }

              $product = \Bitrix\Catalog\ProductTable::getById($productId)->fetch();
              if (!$product) {
                  $errorMsg = "Товар с ID {$productId} не найден в каталоге";
                  file_put_contents(
                      '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_errors.log',
                      date('Y-m-d H:i:s') . " - " . $errorMsg . "\n",
                      FILE_APPEND
                  );
                  continue;
              }

              $basketItem = $basket->createItem('catalog', $productId);
              if (!$basketItem) {
                  $errorMsg = "Не удалось создать позицию в корзине для товара ID: {$productId}";
                  file_put_contents(
                      '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_errors.log',
                      date('Y-m-d H:i:s') . " - " . $errorMsg . "\n",
                      FILE_APPEND
                  );
                  continue;
              }
              $currency = $order->getCurrency();
              if (empty($currency)) {
                  $currency = Bitrix\Currency\CurrencyManager::getBaseCurrency();
                  file_put_contents(
                      '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_debug.log',
                      date('Y-m-d H:i:s') . " - Используем базовую валюту: {$currency}\n",
                      FILE_APPEND
                  );
              }

              // Подготавливаем поля для корзины
              $basketFields = [
                  'QUANTITY' => (float)$item['QUANTITY'],
                  'LID' => $order->getSiteId(),
                  'CURRENCY' => $currency,
                  'PRICE' => (float)$item['PRICE'],
                  'BASE_PRICE' => (float)($item['BASE_PRICE'] ?? $item['PRICE']),
                  'CUSTOM_PRICE' => 'Y',
                  'NAME' => $item['NAME'],
                  // 'PRODUCT_PROVIDER_CLASS' => \Bitrix\Catalog\Product\Basket::getDefaultProviderName(),
                  'PRODUCT_PROVIDER_CLASS' => "\Bitrix\Catalog\Product\CatalogProvider",
              ];

              $setResult = $basketItem->setFields($basketFields);
              // Принудительно вызываем загрузку данных
              // $providerData = CCatalogProductProvider::GetProductData([
              //     'PRODUCT_ID' => $productId,
              //     'QUANTITY'   => $item['QUANTITY'],
              //     'RENEWAL'    => 'N'
              // ]);
              file_put_contents( "/var/www/bitrix/data/www/tempusshop.ru/local/dev/provider_log.log", print_r( $basketFields, 1 ) );

              if (!$setResult->isSuccess()) {
                  $errorMsg = "Ошибка установки полей для товара ID: {$productId} - " .
                             implode(", ", $setResult->getErrorMessages());
                  file_put_contents(
                      '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_errors.log',
                      date('Y-m-d H:i:s') . " - " . $errorMsg . "\n",
                      FILE_APPEND
                  );
              } else {
                  file_put_contents(
                      '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_debug.log',
                      date('Y-m-d H:i:s') . " - Товар ID: {$productId} успешно добавлен в корзину\n",
                      FILE_APPEND
                  );
              }

          } catch (\Exception $e) {
              file_put_contents(
                  '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_errors.log',
                  date('Y-m-d H:i:s') . " - Исключение при обработке товара: " . $e->getMessage() . "\n",
                  FILE_APPEND
              );
              continue;
          }
      }

      try {
    file_put_contents(
        '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_debug.log',
        date('Y-m-d H:i:s') . " - Попытка сохранения корзины\n",
        FILE_APPEND
    );

    // $saveResult = $basket->save();

    // if (!$saveResult->isSuccess()) {
    //     $errorMessages = implode(", ", $saveResult->getErrorMessages());
    //     $errorMsg = "Ошибка сохранения корзины: " . $errorMessages;
    //
    //     file_put_contents(
    //         '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_errors.log',
    //         date('Y-m-d H:i:s') . " - " . $errorMsg . "\n",
    //         FILE_APPEND
    //     );
    //
    //     // Дополнительный дамп для отладки
    //     file_put_contents(
    //         '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_debug.log',
    //         date('Y-m-d H:i:s') . " - Состояние корзины перед ошибкой: " . print_r($basket->getBasketItems()->toArray(), true) . "\n",
    //         FILE_APPEND
    //     );
    //
    //     throw new \Exception($errorMsg);
    // }

    file_put_contents(
        '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_debug.log',
        date('Y-m-d H:i:s') . " - Корзина успешно сохранена\n",
        FILE_APPEND
    );

    } catch (\Exception $e) {
        file_put_contents(
            '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_errors.log',
            date('Y-m-d H:i:s') . " - Критическая ошибка при сохранении корзины: " . $e->getMessage() . "\n",
            FILE_APPEND
        );

        // Дополнительная информация для отладки
        $basketItems = [];
        foreach ($basket as $item) {
            $basketItems[] = [
                'PRODUCT_ID' => $item->getProductId(),
                'QUANTITY' => $item->getQuantity(),
                'PRICE' => $item->getPrice()
            ];
        }

        file_put_contents(
            '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_debug.log',
            date('Y-m-d H:i:s') . " - Содержимое корзины: " . print_r($basketItems, true) . "\n",
            FILE_APPEND
        );

        return false; // Прерываем выполнение при критической ошибке
    }

      // if (!$saveResult->isSuccess()) {
      //     file_put_contents(
      //         '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_errors.log',
      //         date('Y-m-d H:i:s') . " - Ошибка сохранения корзины: " .
      //         implode(", ", $saveResult->getErrorMessages()) . "\n",
      //         FILE_APPEND
      //     );
      // } else {
      //     file_put_contents(
      //         '/var/www/bitrix/data/www/tempusshop.ru/local/dev/order_debug.log',
      //         date('Y-m-d H:i:s') . " - Корзина успешно обновлена\n",
      //         FILE_APPEND
      //     );
      // }
    if (!empty($orderData['STATUS_ID'])) {
        $statusId = mapStatusId($orderData['STATUS_ID']);
        $order->setField('STATUS_ID', $statusId);
    }
    $order->setField('COMMENTS', $orderData["MANAGER_COMMENT"]);
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('1',true). PHP_EOL);
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('###',true). PHP_EOL,FILE_APPEND);

    $order->setField('ACCOUNT_NUMBER', $orderData["ACCOUNT_NUMBER"]);


    $shipmentCollection = $order->getShipmentCollection();
    $shipment = $shipmentCollection->current();
    if ($shipment && !$shipment->isSystem()) {
        $deliveryId = mapDeliveryId($orderData['SHIPMENT'][0]['DELIVERY_ID']);
        $shipment->setFields([
            'DELIVERY_ID' => $deliveryId,
            'PRICE_DELIVERY' => $orderData['DELIVERY_PRICE'],
            'BASE_PRICE_DELIVERY' => $orderData['DELIVERY_BASE_PRICE'],
        ]);
    }
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('2',true). PHP_EOL,FILE_APPEND);
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('###',true). PHP_EOL,FILE_APPEND);
    $paymentCollection = $order->getPaymentCollection();
    $payment = $paymentCollection->current();
    if ($payment) {
        $paySystemId = mapPaySystemId($orderData['PAYMENT'][0]['PAY_SYSTEM_ID']);
        $payment->setFields([
            'PAY_SYSTEM_ID' => $paySystemId,
            'PAY_SYSTEM_NAME' => $orderData['PAYMENT'][0]['PAY_SYSTEM_NAME'],
            'SUM' => $orderData['PAYMENT'][0]['SUM'],
        ]);

        if ($orderData['SUM_PAID'] > 0 && $payment->getSum() <= $orderData['SUM_PAID']) {
            $payment->setPaid('Y');
        }
    }
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('3',true). PHP_EOL,FILE_APPEND);
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('###',true). PHP_EOL,FILE_APPEND);
    $siteId = $order->getSiteId();
    if($siteId === "s2") {
        $userdescr = "tempus.by\nID заказа: {$orderData['ID']}\nКомментарий клиента: {$orderData['USER_DESCRIPTION']}\n";
    } else {
        $userdescr = "tempus.ru\nID заказа: {$orderData['ID']}\nКомментарий клиента: {$orderData['USER_DESCRIPTION']}\n";
        if (!empty($orderData["T_BANK_BILLIN_ID"])) {
            $userdescr .= "Номер транзакции T-Bank: {$orderData["T_BANK_BILLIN_ID"]}\n";
        }
        if (!empty($orderData["T_BANK_STATUS"])) {
            $userdescr .= "Статус оплаты T-Bank: {$orderData["T_BANK_STATUS"]}";
        }
    }
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('4',true). PHP_EOL,FILE_APPEND);
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('###',true). PHP_EOL,FILE_APPEND);
    $order->setField('USER_DESCRIPTION', $userdescr);

    $propertyCollection = $order->getPropertyCollection();
    $propertyFields = [
        'id_order_tempusru' => $orderData['ID'],
        'FIO' => $orderData['USER']['FIO'],
        'EMAIL' => $orderData['USER']['EMAIL'],
        'PHONE' => $orderData['USER']['PERSONAL_PHONE'],
        'LOCATION' => $orderData['USER']['LOCATION'],
        'ADDRESS' => $orderData['USER']['ADDRESS'],
        'ZIP' => $orderData['USER']['ZIP'],
        'IPOLSDEK_CNTDTARIF' => $orderData['USER']['IPOLSDEK_CNTDTARIF']
    ];

    foreach ($propertyFields as $code => $value) {
        $prop = $propertyCollection->getItemByOrderPropertyCode($code);
        if ($prop) {
            $prop->setValue($value);
        }
    }
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('5',true). PHP_EOL,FILE_APPEND);
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('###',true). PHP_EOL,FILE_APPEND);
    $order->doFinalAction(true);
    $GLOBALS['DISABLE_TEMPUS_HANDLER'] = true;
    $result = $order->save();
    unset($GLOBALS['DISABLE_TEMPUS_HANDLER']);
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('6',true). PHP_EOL,FILE_APPEND);
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/aaaa.txt', print_r('###',true). PHP_EOL,FILE_APPEND);
    if ($result->isSuccess()) {
      if ($order->getId()) {
            $newOrderId = $order->getId();
            $externalID = $order->getField('ACCOUNT_NUMBER');
            $tradeBindingCollection = $order->getTradeBindingCollection();
            $tpId = null;
            /** @var Bitrix\Sale\TradeBindingEntity $item */
            foreach ($tradeBindingCollection as $item) {
                $tpId = $item->getField('TRADING_PLATFORM_ID');
                break;
            }

            if (!$tpId) {
                $res = \Bitrix\Sale\TradingPlatform\OrderTable::add(array(
                    "ORDER_ID" => $newOrderId,
                    "TRADING_PLATFORM_ID" => 13,
                    "EXTERNAL_ORDER_ID" => $externalID
                ));

                if (!$res->isSuccess()) {
                    $errors = $res->getErrorMessages();
                }
            }
        }
        return $order->getId();
    } else {
        Debug::writeToFile($result->getErrorMessages(), "Order Update Error", "/local/dev/order_update_errors.txt");
        return false;
    }
}

function mapStatusId($externalStatusId) {
    $statusMap = [
        'AB' => 'AB',  // 150 Уже купил (Не активен)
        'CA' => 'CA',  // 200 Отменен (Клиент) (Не активен)
        'CL' => 'CL',  // 37 Сборка (Активен)
        'CO' => 'CO',  // 30 Готов к доставке (Не активен)
        'CR' => 'CR',  // 60 Выдан курьеру на доставку (Не активен)
        'CS' => 'CS',  // 205 Отменен (Магазин) (Не активен)
        'DA' => 'DA',  // 310 Комплектация заказа (Не активен)
        'DB' => 'DB',  // 130 Дубль заказа (Не активен)
        'DF' => 'DF',  // 400 Отгружен (Активен)
        'DG' => 'DG',  // 320 Ожидаем приход товара (Активен)
        'DK' => 'DK',  // 100 Доставлен курьером (Активен)
        'DN' => 'DN',  // 300 Ожидает обработки (Активен)
        'DO' => 'DO',  // 85 Выполнен, on (Активен)
        'DP' => 'DP',  // 100 Не устроили условия доставки (Не активен)
        'DS' => 'DS',  // 340 Передан в службу доставки (Активен)
        'DT' => 'DT',  // 330 Ожидаем забора транспортной компанией (Активен)
        'F' => 'F',    // 80 Выполнен (Активен)
        'FB' => 'FB',  // 90 Выполнен, без смс (Не активен)
        'LP' => 'LP',  // 100 Нашли дешевле (Не активен)
        'N' => 'N',    // 10 Новый (Активен)
        'NA' => 'NA',  // 120 Не удалось дозвониться (Не активен)
        'NK' => 'NK',  // 100 Не доставлен курьером (Активен)
        'no' => 'no',  // 155 Передумал/Ошибка (Не активен)
        'NZ' => 'NZ',  // 110 Отказ на этапе доставки (Не активен)
        'OS' => 'OS',  // 180 Нет в наличии (Активен)
        'OT' => 'OT',  // 170 Не устроили сроки (Не активен)
        'P' => 'P',    // 65 Прибыл в пункт выдачи (Активен)
        'PO' => 'PO',  // 200 Zamówienie przetworzone (Активен)
        'Pr' => 'Pr',  // 50 Передан в службу доставки (Не активен)
        'PW' => 'PW',  // 70 Ожидает в ПВЗ (Не активен)
        'qd' => 'qd',  // 95 Запрос выполнен (Активен)
        'R' => 'R',    // 75 Возврат в пути (Не активен)
        'RD' => 'RD',  // 115 Возврат после вручения (Не активен)
        'rp' => 'rp',  // 77 Возврат прибыл в ПВЗ (Не активен)
        'SB' => 'SB',  // 40 Ожидаем оплату (Не активен)
        'SE' => 'SE',  // 35 Готов к отправке (Не активен)
        'TA' => 'TA',  // 20 Самовывоз (Не активен)
        'WT' => 'WT',  // 100 Ожидаем поступление (Не активен)
    ];

    return $statusMap[$externalStatusId] ?? 'N';
}

function processOrder($orderData) {
  // die;
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/getdata.txt', print_r($orderData,true). PHP_EOL);
    $order = findOrderByTempusId($orderData['ID']);
    if ($order) {
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/getdata.txt', print_r('INIT UPADTE\n',true). PHP_EOL,FILE_APPEND);
        return updateOrder($order, $orderData);

    } else {
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/getdata.txt', print_r('INIT CREATE\n',true). PHP_EOL,FILE_APPEND);
        return createOrder($orderData);

    }
}

/*
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_data'])) {
    $orderData = json_decode($_POST['order_data'], true);



    $orderId = processOrder($orderData);

    if ($orderId) {
        updateOrderProductsXml($orderId);
        $response = [
            'status' => 'success',
            'new_order_id' => $newOrderId
        ];

        $arOrderLog["ADDED"] = [
          "ORDER_ID" => $orderData['ID'],
          "NEW_ORDER_ID" => $newOrderId,
          "TIME" => date('G:i:s'),
          "STATUS" => 'ADDED',
          "TEXT" => 'Заказ успешно обработан'
        ];
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/orders/s2/'.date("d.m.Y").'.log', print_r(json_encode($arOrderLog),TRUE) . PHP_EOL, FILE_APPEND);
        unset($arOrderLog);

    } else {
        $response = [
            'status' => 'error',
            'message' => 'Ошибка при создании заказа'
        ];

        $arOrderLog["ERROR"] = [
          "ORDER_ID" => $orderData['ID'],
          "NEW_ORDER_ID" => '',
          "TIME" => date('G:i:s'),
          "STATUS" => 'ERROR',
          "TEXT" => 'Ошибка при создании заказа на стороне Бэк-системы'
        ];
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/orders/s2/'.date("d.m.Y").'.log', print_r(json_encode($arOrderLog),TRUE) . PHP_EOL, FILE_APPEND);
        unset($arOrderLog);
    }
    echo json_encode($response);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Неверный запрос'
    ]);

    $arOrderLog["ERROR"] = [
      "ORDER_ID" => $orderData['ID'],
      "NEW_ORDER_ID" => '',
      "TIME" => date('G:i:s'),
      "STATUS" => 'ERROR',
      "TEXT" => 'Ошибка при создании заказа на стороне сайта'
    ];
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/orders/s2/'.date("d.m.Y").'.log', print_r(json_encode($arOrderLog),TRUE) . PHP_EOL, FILE_APPEND);
    unset($arOrderLog);
}
*/