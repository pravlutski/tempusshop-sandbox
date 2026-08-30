<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("engine_ozon_orders_OzonOrderMain_php_IP");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");

use Bitrix\Main\Loader,
    Bitrix\Main\ModuleManager,
    Bitrix\Iblock,
    Bitrix\Catalog,
    Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Config\Option,
    Bitrix\Currency,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem,
    Bitrix\Highloadblock as HL,
    Bitrix\Main\Entity,
    Bitrix\Main\Application,
    Bitrix\Main\Type,
    Bitrix\Main\Web\Json;


Bitrix\Main\Loader::includeModule("main");
Bitrix\Main\Loader::includeModule("sale");
Bitrix\Main\Loader::includeModule("catalog");
Bitrix\Main\Loader::includeModule("iblock");

class OzonOrderMain {

  private $ordersApi; // Массив с заказами озона
  private $api; // Массив с данными для авторизации по апи
  private $db; // Экземпляр класса базы данных
  private $options; // Массив с разными параметрами для добавления заказа в битрикс
  private array $salesNames = [];

  public function __construct( $cabinet )
  {
      if ( !in_array($cabinet, ["IP", "TI", "WT"]) ) die("WRING CABINET\n");
      global $DB;
      $this->db = $DB;
      CModule::IncludeModule('panel.manager');
      $this->dbPanel = new DBPanel;
      $res = $this->dbPanel->select(['*'], "ozon_main_settings_{$cabinet}")->make();
  		foreach ( $res as $row ){
  		  $this->api[ $row['name'] ] = $row['value'];
  		}
      if ( $cabinet == "IP" ){
        $this->options = [
          // 'user_id' => 172921, // Тестовый пользователь
          'user_id' => 182118, //ID пользователя, от имени которого будут создаваться заказы в битриксе (fbs ozon ti)
          'site_id' => 's1', // ID сайта (tempusshop.ru)
          'pay_id' => 34, // ID платежной системы (Оплата при получении)
          'ship_id' => 68, // ID службы доставки (Доставка)
          'person_type' => 1, // ID типа плательщика (Физическое лицо)
          'currency' => 'RUB',
          'status_table_name' => 'wdhs_ozon_order_status', // Таблица с соответствиями статусов
          'order_table_name' => 'wdhs_ozon_orders', // Таблица с данными о заказах
          'products_table_name' => 'wdhs_ozon_order_products', // Таблица с данными о товарах из заказов
          'customer_table_name' => 'wdhs_ozon_order_customers', // Таблица с данными о покупателях
          'cost_ms_table_name' => 'wdhs_ozon_fbo_sebes_new', // Таблица с себестоимостью товаров FBO
          'allowed_statuses' => ['awaiting_packaging', 'awaiting_registration', 'awaiting_deliver'], // Статусы, которые позволяют создавать заказы
          'log_path' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/logs/IP/' . date('Y-m-d') . '_orders.txt'
        ];
      }else if( $cabinet == 'TI' ){
        $this->options = [
          // 'user_id' => 172921, // Тестовый пользователь
          'user_id' => 172147, //ID пользователя, от имени которого будут создаваться заказы в битриксе (fbs ozon ti)
          'site_id' => 's1', // ID сайта (tempusshop.ru)
          'pay_id' => 34, // ID платежной системы (Оплата при получении)
          'ship_id' => 68, // ID службы доставки (Доставка)
          'person_type' => 1, // ID типа плательщика (Физическое лицо)
          'currency' => 'RUB',
          'status_table_name' => 'wdhs_ozon_order_status_ti', // Таблица с соответствиями статусов
          'order_table_name' => 'wdhs_ozon_orders_ti', // Таблица с данными о заказах
          'products_table_name' => 'wdhs_ozon_order_products_ti', // Таблица с данными о товарах из заказов
          'customer_table_name' => 'wdhs_ozon_order_customers_ti', // Таблица с данными о покупателях
          'cost_ms_table_name' => 'wdhs_ozon_fbo_sebes_new', // Таблица с себестоимостью товаров FBO
          'allowed_statuses' => ['awaiting_packaging', 'awaiting_registration', 'awaiting_deliver'], // Статусы, которые позволяют создавать заказы
          'log_path' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/logs/TI/' . date('Y-m-d') . '_orders.txt'
        ];
      }else if( $cabinet == 'WT' ){
        $this->options = [
          // 'user_id' => 172921, // Тестовый пользователь
          'user_id' => 193181, //ID пользователя, от имени которого будут создаваться заказы в битриксе (fbs ozon ti)
          'site_id' => 's2', // ID сайта (tempusshop.ru)
          'pay_id' => 34, // ID платежной системы (Оплата при получении)
          'ship_id' => 68, // ID службы доставки (Доставка)
          'person_type' => 1, // ID типа плательщика (Физическое лицо)
          'currency' => 'BYN',
          'status_table_name' => 'wdhs_ozon_order_status', // Таблица с соответствиями статусов
          'order_table_name' => 'wdhs_ozon_orders_wt', // Таблица с данными о заказах
          'products_table_name' => 'wdhs_ozon_order_products_wt', // Таблица с данными о товарах из заказов
          'customer_table_name' => 'wdhs_ozon_order_customers_wt', // Таблица с данными о покупателях
          'cost_ms_table_name' => 'wdhs_ozon_fbo_sebes_new', // Таблица с себестоимостью товаров FBO
          'allowed_statuses' => ['awaiting_packaging', 'awaiting_registration', 'awaiting_deliver'], // Статусы, которые позволяют создавать заказы
          'log_path' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/logs/WT/' . date('Y-m-d') . '_orders.txt'
        ];
      }
      $this->cabinet = $cabinet;
      // Важно! Если вы редактируете массив выше, редактируйте также комментарии
  }

  public function run()
  {
    $this->writeLog('START');
    $this->getSalesDictionary();
    $this->loadOrdersOzon();
    $this->processOrders();
    $this->writeLog('END');
  }

  private function getSalesDictionary():void
  {
    $strSql = "SELECT model, saleName FROM ozon_sales_detail_log_IP WHERE date = (SELECT max(date) FROM ozon_sales_detail_log_IP) AND status = 'Y'";
    $rows = $this->dbPanel->query( $strSql );
    $rows = $this->dbPanel->fetchAll( $rows );
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row['saleName'];
    }

    $this->salesNames = $result;
  }

  public function request( $url, $headers = [], $body = '' )
  {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    $res = curl_exec( $ch );
    if ( curl_errno( $ch ) ) {
      $error_msg = curl_error( $ch );
    }
    curl_close( $ch );

    if ( $error_msg ) {
      $this->writeLog('CUrl returned an error: ' . $error_msg);
      return false;
    }

    return json_decode( $res, true );
  }

  public function loadOrdersOzon():void
  {
    // Озон отдаетт время по москве, а сам принимает по гринвичу, поэтому минус три часа
    $data = [
      'filter' => [
        'since' => date( 'Y-m-d', strtotime('- 2 day') ) . 'T20:00:00Z',
        'to' => date( 'Y-m-d\TG:i:s\Z', strtotime('- 3 hours') ),
        // 'to' => date( 'Y-m-d\TG:i:s\Z' ),
        // 'to' => date( 'Y-m-d', strtotime('- 2 day') ) . 'T20:00:00Z',
      ],
      'limit' => 1000,
      'offset' => 0,
      'with' => [
        'analytics_data' => true,
        'barcodes' => true,
        'financial_data' => true,
        'translit' => true
      ]
    ];
    // var_dump($data);
    // die;
    $flag = true;
    $postings = [];
    while ( $flag ){
      $res = $this->request(
        $this->api['api_url'] . '/v3/posting/fbs/list',
        [
          'Api-Key:' . $this->api['key'],
          'Client-Id:' . $this->api['client_id'],
          'Content-Type:application/json'
        ],
        json_encode( $data, JSON_UNESCAPED_UNICODE )
      );

      $postings = $postings + $res['result']['postings'];

      if ( count($res['result']['postings']) < 1000 || empty($res) ){
        $flag = false;
      }
      $data['offset'] += $data['limit'];
      sleep(1);
    }
    if( empty($postings) ) {
      $this->writeLog('OZON had returned no orders. Check if request body is valid or there were no orders');
      return;
    }
    // if( ! is_array( $res ) || empty( $res['result']['postings'] ) ) {
    //   $this->writeLog('OZON had returned no orders. Check if request body is valid or there were no orders');
    //   return;
    // }

    $postings = $res['result']['postings'];

    $orderDataTmp = [];
    foreach( $postings as $field ){
      // Общая информация по заказу
      if ($field['order_id'] == '30624070882') {
        print_r($field);
      }
      $orderDataTmp = [
        'order_id' => $field['order_id'],
        'order_number' => $field['order_number'],
        'posting_number' => $field['posting_number'],
        'in_process_at' => date( 'Y-m-d G:i:s', strtotime($field['in_process_at']) ),
        'shipment_date' => date( 'Y-m-d G:i:s', strtotime($field['shipment_date']) ),
        'status' => $field['status'],
        'delivery_type' => 'fbs',
        'timestamp' => time(),
        'financial_data' => $field['financial_data'],
        'lower_barcode' => $field['barcodes']['lower_barcode']
      ];
        // Информация по клиенту. Пока ничего кроме NULL не видел, но на всякий случай
      if ( !empty($field['customer']) ){
        $orderDataTmp['customer'] = [
          'name' => $field['customer']['name'],
          'address_tail' => $field['customer']['address']['address_tail'],
          'country' => $field['customer']['address']['country'],
          'region' => $field['customer']['address']['region'],
          'pvz_code' => $field['customer']['address']['pvz_code'],
          'city' => $field['customer']['address']['city'],
          'comment' => $field['customer']['address']['comment'],
          'posting_number' => $field['posting_number'],
        ];
      } else {
        $orderDataTmp['customer'] = [];
      }
        // Обрабатываем товары в заказе
      $products = $field['products'];
      foreach ( $products as $product ){
        $orderItem = self::getItemByOfferId( $product['offer_id'] );
        $orderDataTmp['products'][] = [
          'quantity' => $product['quantity'],
          'price' => $product['price'],
          'currency_code' => $product['currency_code'],
          'bitrix_id' => $orderItem['bitrix_id'],
          'xml_id' => $orderItem['xml_id'],
          'vendor_code' => $orderItem['vendor_code'],
          'name' => $orderItem['NAME'],
          'posting_number' => $field['posting_number'],
          'base_price' => $orderItem['BASE_PRICE'],
          'saleName' => $this->salesNames[ $orderItem['vendor_code'] ] ?? 'NULL',
        ];
      }
      $this->ordersApi[] = $orderDataTmp;
      unset( $orderDataTmp );
    }
    // die();
    // $tmp[] = $this->ordersApi[1]; // Тест. Один заказ.
    // $this->ordersApi = $tmp;
    $this->writeLog('Got orders from OZON: ' . count($this->ordersApi));
    // die;
  }

  public function processOrders():void
  {
    if ( empty($this->ordersApi) ){
      $this->writeLog('OZON had returned no orders. Check if gathered data is valid or there were no orders');
      return;
    }
    foreach ( $this->ordersApi as &$orderApi ){

      if ( $this->checkIfOrderExists($orderApi) ){
        if ( !( $statusTmp = $this->mapStatus($orderApi['status']) ) ){ //Если метод возвращает false, заходим в условие

            $this->writeLog("Match for the status '{$orderApi['status']}' got from OZON is not set. '{$orderApi['posting_number']}' will not be created");
            continue;
        }
        // if ( !in_array($orderApi['status'], $this->options['allowed_statuses']) ){
        //   $this->writeLog("Order '{$orderApi['posting_number']}' has incorrect status '{$orderApi['status']}' Check the statuses allowed");
        //   continue;
        // }
        $this->writeLog( $orderApi['posting_number'] . ' is new. Trying to add' );
        // Блок, где заказ создается в битриксе
        $currency = strval($this->options['currency'] ?? 'RUB');
        $siteId = strval($this->options['site_id'] ?? 's1');

        $orderBX = \Bitrix\Sale\Order::create($siteId, $this->options['user_id'], $currency );

        // Работаем с корзиной
        $basket = \Bitrix\Sale\Basket::create( $siteId );
        $orderBX->setBasket($basket);
        $orderSum = 0;
        foreach ( $orderApi['products'] as $product ){
          $arItem = [
            'PRODUCT_ID' => intval( $product['bitrix_id'] ),
            'CATALOG_XML_ID' => intval( $product['xml_id'] ),
            'NAME' => strval( $product['name'] ),
            'BASE_PRICE' => floatval( $product['price'] ),
            'PRICE' => floatval( $product['price'] ),
            // 'CURRENCY' => strval( $product['currency_code'] ),
            'CURRENCY' => $orderBX->getCurrency(),
            'QUANTITY' => intval( $product['quantity'] ),
            'LID' => strval( $this->options['site_id'] ),
            // 'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
            'CUSTOM_PRICE' => 'Y'
          ];
          $currencyCode = $product['currency_code'];
          $basketItem = $basket->createItem( 'catalog', $arItem['PRODUCT_ID'] );
          $basketItem = $basketItem->setFields( $arItem );
          $orderSum += floatval( $product['price'] * $product['quantity'] );
        }

        // Формируем комментарий к заказу
        $financial_data = '';
        if( is_array($orderApi['financial_data']['products']) ) {
            foreach ( $orderApi['financial_data']['products'] as $data ) {
                $financial_data .= "\n commission_amount - {$data['commission_amount']}, ";
                $financial_data .= "\n commission_percent - {$data['commission_percent']}, ";
                $financial_data .= "\n price - {$data['price']}, ";
                $financial_data .= "\n product_id - {$data['product_id']}, ";
                $financial_data .= "\n quantity - {$data['quantity']}, ";
            }
            $financial_data .= "\n order_number - {$orderApi['order_number']}";
        }

        // Работаем с заказом
        $orderBX->setPersonTypeId( $this->options['person_type'] );
        $orderBX->setFields([
          // 'CURRENCY' => strval( $currencyCode ),
          'USER_DESCRIPTION' => 'Заказ поступил с Ozon.' . $financial_data,
          // 'USER_DESCRIPTION' => 'Ozon TEST',
          'COMMENTS' => 'Заказ поступил с Ozon ' . $this->cabinet,
          'STATUS_ID' => $this->mapStatus( $orderApi['status'] )
        ]);
        // $orderBX->setBasket( $basket );

        // Сохраненяем пользовательские свойства
        $propertyCollection = $orderBX->getPropertyCollection();
        $customerDetails = $this->getCustomerDetails( $orderApi['customer'] );
        $propertyFields = [
          'tpl_integration_type' => 'ozon',
          'OZON_NUMBER' => trim( $orderApi["posting_number"] ),
          'OZON_DATE' => date( 'd.m.Y', strtotime($orderApi['in_process_at']) ),
          'OZON_LOWER_BARCODE' => $orderApi['lower_barcode'],
          'OZON_ORDER_TYPE' => empty( $orderApi['customer'] ) ? 'FBS' : 'rFBS',
          'PHONE' => $customerDetails['phone'],
          'FIO' => $customerDetails['fio'],
          'EMAIL' => $customerDetails['email'],
          'ADDRESS' => $customerDetails['address'],
        ];
        foreach ( $propertyFields as $code => $value ) {
            $prop = $propertyCollection->getItemByOrderPropertyCode( $code );
            if ( $prop ) $prop->setValue( $value );
        }

        // Добавляем службу доставки
        $shipmentCollection = $orderBX->getShipmentCollection();
        $shipment = $shipmentCollection->createItem();
        $service = Delivery\Services\Manager::getById( $this->options['ship_id'] );
        $shipment->setFields([
            'DELIVERY_ID' => $service['ID'],
            'DELIVERY_NAME' => $service['NAME'],
        ]);
        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        foreach ($basket as $item) {
            $shipmentItem = $shipmentItemCollection->createItem( $item );
            $shipmentItem->setQuantity( $item->getQuantity() );
        }

        // Добавляем платежную систему
        $paymentCollection = $orderBX->getPaymentCollection();
        $payment = $paymentCollection->createItem();
        $paySystemService = PaySystem\Manager::getObjectById( $this->options['pay_id'] );
        $payment->setFields([
            'PAY_SYSTEM_ID' => $paySystemService->getField('PAY_SYSTEM_ID'),
            'PAY_SYSTEM_NAME' => $paySystemService->getField('NAME'),
            'SUM' => $orderSum,
        ]);

        // Сохраняем заказ
	    $orderBX->doFinalAction(true);
        $result = $orderBX->save();
        if ( $result->isSuccess() ){
          $orderApi['order_bid'] = $orderBX->getId();
          $this->writeInDatabase( $orderApi );
          $this->writeLog("Order '{$orderApi['posting_number']}' is saved successfully");

          $orderId = $orderBX->getId();
          $externalID =  $orderBX->getField('ACCOUNT_NUMBER');
          $tpId = $this->cabinet == 'WT' ? 20 : 8;
          try{
            $resss = \Bitrix\Sale\TradingPlatform\OrderTable::add(array(
              "ORDER_ID" => $orderId,
              "TRADING_PLATFORM_ID" => $tpId,
              "EXTERNAL_ORDER_ID" => $externalID
            ));
          }catch( Throwable $e ){
            var_dump( $orderId );
            var_dump($e->getMessage());
          }
        }else{
          $this->writeLog( 'Failed to save order: ' . $result->getError() );
        }

        // Тест
        // $orderApi['order_bid'] = rand(111111,999999);
        // $this->writeInDatabase( $orderApi );
      }else{
        $upp = $this->checkZeroCode($orderApi);
        if ($upp) {

        }
        $this->writeLog("Order '{$orderApi['posting_number']}' already exists. Trying to update status");
        $this->updateOrderStatus( $orderApi );
      }
    }
  }

  private function writeInDatabase( array $order ):void
  {

    if ( empty($order) ){
      $this->writeLog('Method writeInDatabase returned an exception (empty array). Check if gathered data is valid');
      throw new \Exception("No empty array allowed. method: writeInDatabase");
    }
    // Записываем заказы в таблицу
    $orderProducts = $order['products'];
    $orderCustomer = $order['customer'];
    unset($order['products']);
    unset($order['customer']);
    unset($order['financial_data']);
    $strSql = $this->formulateRequest( $order, $this->options['order_table_name'] );
    // var_dump($strSql);
    $this->db->Query($strSql, false, $err_mess.__LINE__);
    //Записываем заказанные товары в отдельную таблицу
    foreach ( $orderProducts as $product ){
      unset( $product['xml_id'] );
      $productData = $this->getProductCostAndSupplier( $product['vendor_code'] );
      $product['cost'] = $productData['price'] ?? 0;
      $product['supplier'] = $productData['supplier'] ?? 0;
      $strSql = $this->formulateRequest( $product, $this->options['products_table_name'] );
      // var_dump($strSql);
      $this->db->Query($strSql, false, $err_mess.__LINE__);
    }
    //Записываем покупателей в отдельную таблицу
    if ( !empty($orderCustomer) ){
        $strSql = $this->formulateRequest( $orderCustomer, $this->options['customer_table_name'] );
        // var_dump($strSql);
        $this->db->Query($strSql, false, $err_mess.__LINE__);

    }
  }

  private function updateOrderStatus( array $order ):void
  {
    if ( empty($order) ){
      $this->writeLog('Method updateOrderStatus returned an exception (empty array). Check if gathered data is valid');
      throw new \Exception("No empty array allowed. method: updateOrderStatus");
    }
    if ( $statusBX = $this->mapStatus($order['status']) ){

      $strSql = "SELECT status, order_bid FROM {$this->options['order_table_name']} WHERE posting_number = '{$order['posting_number']}'";
      $result = $this->db->Query($strSql, false, $err_mess.__LINE__)->Fetch();
      $orderBX = Order::load( $result['order_bid'] );

      // if ( !empty($result['status']) && ($result['status'] != $order['status'] || $orderBX->getField("STATUS_ID") != $statusBX) ){
      if ( $result['status'] != $order['status'] || $orderBX->getField("STATUS_ID") != $statusBX ){
        if ( $orderBX->getField('STATUS_ID') == 'F' ){
          $this->writeLog("Order '{$order['posting_number']}' has status F - completed and cannot be updated in auto mode");
          return;
        }

        $orderBX->setField('STATUS_ID', $statusBX);
        $strSql = "UPDATE {$this->options['order_table_name']} SET status = '{$order['status']}' WHERE posting_number = '{$order['posting_number']}'";
        print_r( $strSql );
        $result = $this->db->Query($strSql, false, $err_mess.__LINE__);

        $resultObj = $orderBX->save();
        if ( $order['posting_number'] == '87753866-0304-2' ){
          var_dump($order);
          var_dump($statusBX);
          var_dump($resultObj);
        }
        if ( $resultObj->isSuccess() ){
          $this->writeLog("Status for '{$order['posting_number']}' is updated");
        }else{
          $this->writeLog("Status update for '{$order['posting_number']}' failed: " . $resultObj->getError() );
        }
      }else{
        $this->writeLog("Status for '{$order['posting_number']}' is already up to date");
      }

    }else{
      $this->writeLog("Match for the status '{$order['status']}' got from OZON is not set. '{$order['posting_number']}' will not be updated");
    }
  }

  private function checkZeroCode( array $order ):bool
  {
    if ( empty($order) ){
      $this->writeLog('Method checkIfOrderExists returned an exception (empty array). Check if gathered data is valid');
      throw new \Exception("No empty array allowed. method: checkIfOrderExists");
    }
          // Проверяем наличие записи в твоей таблице
      $strSql = "SELECT id, lower_barcode
                 FROM {$this->options['order_table_name']}
                 WHERE posting_number = '{$order['posting_number']}'";

      $result = $this->db->Query($strSql, false, $err_mess.__LINE__);

      if ($arRow = $result->Fetch()) {
          // если пусто или равно "0" → обновляем

          if (empty($arRow['lower_barcode']) || $arRow['lower_barcode'] == "0") {
              file_put_contents( '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/testUPP.txt', print_r('###',true) . PHP_EOL, FILE_APPEND );
              file_put_contents( '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/testUPP.txt', print_r($order,true) . PHP_EOL, FILE_APPEND );
              $updateSql = "UPDATE {$this->options['order_table_name']}
                            SET lower_barcode = '{$order['lower_barcode']}'
                            WHERE id = {$arRow['id']}";
              $this->db->Query($updateSql, false, $err_mess.__LINE__);


              // === Поиск заказа в Bitrix ===
              $arFilterOrder = [
                  'PROPERTY_VAL_BY_CODE_OZON_NUMBER' => $order['posting_number'],
              ];
              $rsOrders = \CSaleOrder::GetList(
                  ['DATE_INSERT' => 'DESC'],
                  $arFilterOrder
              );

              if ($arOrder = $rsOrders->Fetch()) {
                  $orderId = $arOrder['ID'];

                  $dbProp = \CSaleOrderPropsValue::GetList(
                      [],
                      ['ORDER_ID' => $orderId, 'CODE' => 'OZON_LOWER_BARCODE']
                  );

                  if ($arProp = $dbProp->Fetch()) {
                      \CSaleOrderPropsValue::Update(
                          $arProp['ID'],
                          ['VALUE' => $order['lower_barcode']]
                      );
                  } else {
                      $dbOrderProp = \CSaleOrderProps::GetList([], ['CODE' => 'OZON_LOWER_BARCODE']);
                      if ($arOrderProp = $dbOrderProp->Fetch()) {
                          \CSaleOrderPropsValue::Add([
                              'ORDER_ID' => $orderId,
                              'ORDER_PROPS_ID' => $arOrderProp['ID'],
                              'NAME' => $arOrderProp['NAME'],
                              'CODE' => $arOrderProp['CODE'],
                              'VALUE' => $order['lower_barcode'],
                          ]);
                      }
                  }
              }
              return true;
          } else {
            return false;
          }
      } else {
        return false;
      }

  }

  private function checkIfOrderExists( array $order ):bool
  {
    if ( empty($order) ){
      $this->writeLog('Method checkIfOrderExists returned an exception (empty array). Check if gathered data is valid');
      throw new \Exception("No empty array allowed. method: checkIfOrderExists");
    }
    $strSql = "SELECT 1 FROM {$this->options['order_table_name']} WHERE posting_number = '{$order['posting_number']}'";
    $result = $this->db->Query($strSql, false, $err_mess.__LINE__);
    if ( $result->SelectedRowsCount() > 0 ){
      return false;
    }else{
      // Если заказа нет в таблице, но есть в битриксе, добавляем его.
      $arFilterOrder = array(
        'PROPERTY_VAL_BY_CODE_OZON_NUMBER' => $order['posting_number'],
      );
      $rsOrders = \CSaleOrder::GetList(
        array('DATE_INSERT' => 'DESC'),
        $arFilterOrder
      );
      if ( $arOrder = $rsOrders->Fetch() ){
        $order['order_bid'] = $arOrder['ID'];
        $this->writeLog("'{$order['posting_number']}' was in bitrix but not in the table. Trying to add.");
        $this->writeInDatabase( $order );
        return false;
      }
    }
    return true;
  }

  private function getItemByOfferId( string $offerId ):array
  {
    $vendorCode = end( explode('_', $offerId) );
    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_CML2_ARTICLE' => $vendorCode
    ];
    $arSelect = ['IBLOCK_ID', 'ID', 'XML_ID', 'PROPERTY_CML2_ARTICLE', 'WEIGHT', 'LENGTH', 'WIDTH', 'HEIGHT', 'VAT_ID', 'VAT_INCLUDED', 'DETAIL_PAGE_URL','NAME', 'PROPERTY_OZSB_PRICE'];

    $dp = $this->getDPPrices();

    $result = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
    $item = [];
    while( $row = $result->GetNext() ){
    	$item = [
        'bitrix_id' => $row['ID'],
        'xml_id' => $row['XML_ID'],
        'vendor_code' => $row['PROPERTY_CML2_ARTICLE_VALUE'],
        'WEIGHT' => $row['WEIGHT'],
        'LENGTH' => $row['LENGTH'],
        'WIDTH' => $row['WIDTH'],
        'HEIGHT' => $row['HEIGHT'],
        'VAT_ID' => $row['VAT_ID'],
        'VAT_INCLUDED' => $row['VAT_INCLUDED'],
        'DETAIL_PAGE_URL' => $row['DETAIL_PAGE_URL'],
        'NAME' => $row['NAME'],
        'BASE_PRICE' => $dp[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ] ?? $row['PROPERTY_OZSB_PRICE_VALUE'],
      ];
    }
    return $item;
  }

  private function getDPPrices():array
  {
    $rows = $this->dbPanel->select(['model', 'price'], 'ozon_dp_prices')->make();

    if ( empty($rows) ) return [];

    return array_column( $rows, 'price', 'model' );
  }

  private function getProductCostLEG( string $vendorCode, int $quantity ):float
  {
    // Возвращаем себестоимость из Моего Склада, если установлена в таблице
    // $strSql = "SELECT sebes FROM {$this->options['cost_ms_table_name']} WHERE model = '{$vendorCode}'";
    // $costMS = $this->db->Query($strSql, false, $err_mess.__LINE__)->Fetch()['sebes'];
    // if ( !empty( $costMS ) ) return $costMS;

    // Получаем количество зарезервированных единиц товара и вычитаем заказанное количество, т.к. оно включено
    $strSql = "SELECT RESERVED FROM ci_reserved WHERE ARTICLE = '{$vendorCode}'";
    $reserved = $this->db->Query($strSql, false, $err_mess.__LINE__)->Fetch()['RESERVED'] ?? 0 - $quantity;
    if ( $reserved < 0 ) $reserved = 0;

    // Получаем данные о себестоимости
    $strSql = "SELECT price, count FROM ci_price WHERE model = '{$vendorCode}' AND active_os = 'Y' ORDER BY price ASC";
    $result = $this->db->Query($strSql, false, $err_mess.__LINE__);

    while ( $row = $result->Fetch() ){
      if ( isset($currentReserved) ){
        if ( $currentReserved <= 0 ) {
          $currentReserved = $row['count'] - abs( $currentReserved );
          if ( $currentReserved <= 0 ) continue;
        }
        return floatval($row['price']);
      }else{
        $currentReserved = $row['count'] - $reserved;
        if ( $currentReserved <= 0 ) continue;
        return floatval($row['price']);
      }
    }
    return 0;
  }

  private function getProductCostAndSupplier( string $vendorCode ):array
  {
    if ( empty($vendorCode) ) return [];

    $strSql = "SELECT * FROM ci_price_set WHERE ARTICLE = '{$vendorCode}' AND PRICE_TYPE = 'OS'";
    $rows = $this->db->Query($strSql);
    $result = [
      'price' => 0,
      'supplier' => 999,
    ];

    while( $row = $rows->Fetch() ){
      $result['price'] = $row['PRICE_SUPPLIER'];
      $result['supplier'] = $row['SUPPLIER_ID'];
    }

    return $result;
  }

  private function getCustomerDetails( array $customer ):array
  {
    $result = [];
    if ( !empty($customer) && is_array($customer) ) {
      $result = [
        'fio' => $customer['name'],
        'phone' => $customer['phone'],
        'email' => $customer['pvz_code'] . '@orderTest.ru',
        'address' => "{$customer['address_tail']}, PVZ {$customer['pvz_code']}, {$customer['comment']}"
      ];
    }else{ // Если от озона не пришли данные, то тянем их из юзера
      $rsUser = CUser::GetByID( $this->options['user_id'] );
      $arUser = $rsUser->Fetch();
      if ( !empty($arUser) ){
        $result = [
          'fio' => $arUser["NAME"] . ' ' . $arUser["SECOND_NAME"] . ' ' . $arUser["LAST_NAME"],
          'phone' => $arUser['PERSONAL_PHONE'],
          'email' => $arUser['EMAIL'],
          'address' => $arUser['PERSONAL_CITY']
        ];
      }
    }
    return $result;
  }

  private function mapStatus( string $statusOZ ):string|bool
  {
    if ( empty($statusOZ) ) return false;
    $strSql = "SELECT status_bx FROM {$this->options['status_table_name']} WHERE status_oz = '{$statusOZ}'";
    $result = $this->db->Query($strSql, false, $err_mess.__LINE__);
    if ( $result->SelectedRowsCount() < 0 ){
      return false;
    }
    $statusBX = $result->Fetch()['status_bx'] ?? false;

    return $statusBX;
  }

  private function formulateRequest( array $array, string $table ):string // Люблю велосипеды изобретать
  {
    $columnsDB = "(";
    $valuesDB = "(";
    foreach ( $array as $column => $value ){
      if ( array_key_last($array) == $column ){
        $columnsDB .= $column . ")";
        $valuesDB .= "'{$value}')";
      }else{
        $columnsDB .= $column . ", ";
        $valuesDB .= "'{$value}', ";
      }
    }
    $strSql = "INSERT INTO {$table} {$columnsDB} VALUES {$valuesDB}";
    return $strSql;
  }

  private function writeLog( string $message ):void
  {
    file_put_contents( $this->options['log_path'], date('Y-m-d G:i:s') . ' --- ' . $message . PHP_EOL, FILE_APPEND );
  }

}

( new OzonOrderMain( $argv[1] ?? "IP" ) )->run();
 ?>
