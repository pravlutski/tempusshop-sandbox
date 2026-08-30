<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("engine_wb_orders_WBOrderLost_php_WR");
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

class WBOrderMain {

  private $ordersApi; // Массив с заказами озона
  private $api; // Массив с данными для авторизации по апи
  private $db; // Экземпляр класса базы данных
  private $options; // Массив с разными параметрами для добавления заказа в битрикс
  private $lostOrders = 0;

  public function __construct( $cabinet = 'WR' )
  {
    if ( !in_array($cabinet, ['WT', 'WR', 'TL']) ){
      throw new InvalidArgumentException("Undefined cabinet");
    }
    $this->options = [
      'cabinet' => $cabinet,
      // 'user_id' => 172921, // Тестовый пользователь
      // 'user_id' => 135989, //ID пользователя, от имени которого будут создаваться заказы в битриксе ( wb )
      'site_id' => 's1', // ID сайта (tempusshop.ru)
      'pay_id' => 34, // ID платежной системы (Оплата при получении)
      'ship_id' => 68, // ID службы доставки (Доставка)
      'person_type' => 1, // ID типа плательщика (Физическое лицо)
      'currency_code' => 'RUB', // Код валюты
      'default_status' => 'CL', // Статус, с которым будут создаваться заказы
      'order_table_name' => 'wdhs_wb_orders', // Таблица с данными о заказах
      'products_table_name' => 'wdhs_wb_order_products', // Таблица с данными о товарах из заказов
      'cost_ms_table_name' => 'wdhs_wb_fbo_sebes', // Таблица с себестоимостью товара FBO
      'log_path' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/orders/lost/' . $cabinet . '/orders' . date('Y-m-d') . '_orders_lost.txt',
      // 'log_path' => $_SERVER["DOCUMENT_ROOT"] . '/admin/modules/forTest/' . date('Y-m-d') . '_orders.txt'
    ];
    switch ($cabinet) {
      case 'WR':
        $this->options['user_id'] = 135989;
        break;
      case 'TL':
        $this->options['user_id'] = 161898;
        break;
      case 'WT':
        $this->options['user_id'] = 191551;
        $this->options['site_id'] = 's2';
        break;
      default:
        die("Invalid cabinet\n");
        break;
    }
    $this->cabinet = $cabinet;
    global $DB;
    $this->db = $DB;
    $strSql = "SELECT * FROM wdhs_wb_main_settings WHERE cabinet = '{$this->options['cabinet']}'";
    // var_dump( $strSql );
  	$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
  	$this->api = $results->Fetch();
    // var_dump( $strSql );

    // Важно! Если вы редактируете массив выше, редактируйте также комментарии
  }

  public function run()
  {
    $this->writeLog('START');
    $this->loadOrdersWB();
    $this->processOrders();
    var_dump( 'count: ' . $this->lostOrders );
    $this->writeLog('END');
  }

  public function request( $url, $headers = [], $body = '' )
  {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'GET');
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

  public function loadOrdersWB():void
  {

    $limit = 1000;
    $next = 0;
    $dateFrom = strtotime(date('Y-m-d') . ' 00:00:00' . '- 72 hour');
    $dateTo = time();
    // $dateFrom = strtotime('2025-09-01' . ' 00:00:00' . '- 14 hour');
    // $dateTo = strtotime('2025-09-02' . ' 00:00:00');
    // $dateTo = strtotime(date('Y-m-d') . ' 00:00:00' . '- 1 day');
    $link = "https://marketplace-api.wildberries.ru/api/v3/orders?limit={$limit}&next={$next}&dateFrom={$dateFrom}&dateTo={$dateTo}";
    // var_dump($link);
    $res = $this->request(
      $link,
      [
        "Content-Type: application/json",
				"Authorization: " . $this->api['api']
      ]
    );
    // file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/orders/lost_answer_wb.txt',print_r($res,true).PHP_EOL,FILE_APPEND);
   // var_dump($res);
   // die;
    if( ! is_array( $res ) || empty( $res['orders'] ) ) {
      $this->writeLog('WB had returned no orders. Check if request body is valid or there were no new orders');
      return;
    }

    $orderDataTmp = [];
    foreach( $res['orders'] as $field ){
      // if(intval($field['id']) == 3802610150) {
        // Общая информация по заказу
        $orderDataTmp = [
          'order_id' => intval($field['id']),
          'uid' => strval($field['orderUid']),
          'rid' => strval($field['rid']),
          'created_at' => date( 'Y-m-d G:i:s', strtotime($field['createdAt']) ),
          'warehouse_id' => intval($field['warehouseId']),
          'delivery_type' => strval($field['deliveryType']),
          'cabinet' => strval($this->options['cabinet']),
          'has_sticker' => 'N',
          'timestamp' => time(),
        ];
        $orderDataTmp['address'] = empty($field['address']) ? '' : $field['address']['fullAddress'];

        // Обрабатываем товары в заказе
        $orderItem = self::getItemByOfferId( $field['article'] );
        $orderDataTmp['products'][] = [
          'bitrix_id' => $orderItem['bitrix_id'],
          'vendor_code' => $orderItem['vendor_code'],
          'name' => $orderItem['NAME'],
          'quantity' => 1, // Сама цифра в ответе не фигурирует, но в каждом сборочном задании всегда только один товар
          'nmid' => $field['nmId'],
          'order_id' => $field['id'],
          'price' => $field['convertedPrice'] / 100,
        ];
        $this->ordersApi[] = $orderDataTmp;
        unset( $orderDataTmp );

    }

    $this->writeLog('Got orders from WB: ' . count($this->ordersApi));
  }

  public function processOrders():void
  {
    if ( empty($this->ordersApi) ){
      $this->writeLog('WB had returned no orders. Check if gathered data is valid or there were no new orders');
      return;
    }
    foreach ( $this->ordersApi as &$orderApi ){

      if ( $this->checkIfOrderExists($orderApi) ){
        $this->writeLog("Order '{$orderApi['order_id']}' already exists");
        continue;
      }
      $this->lostOrders++;
      // var_dump($orderApi['order_id']);
      $this->writeLog( "Order '{$orderApi['order_id']}' is new. Trying to save" );
      // file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/orders/checkSJU.txt',print_r('START',true).PHP_EOL,FILE_APPEND);
      // file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/orders/checkSJU.txt',print_r($orderApi,true).PHP_EOL,FILE_APPEND);
      $currency = strval($this->options['currency_code'] ?? 'RUB');
      $siteId = strval($this->options['site_id'] ?? 's1');

      $orderBX = \Bitrix\Sale\Order::create($siteId, $this->options['user_id'], $currency );
      // Работаем с корзиной
      $basket = \Bitrix\Sale\Basket::create( $siteId );
      $orderSum = 0;
      foreach ( $orderApi['products'] as $product ){
        $arItem = [
          'PRODUCT_ID' => intval( $product['bitrix_id'] ),
          'NAME' => strval( $product['name'] ),
          'BASE_PRICE' => floatval( $product['price'] ),
          'PRICE' => floatval( $product['price'] ),
          'CURRENCY' => strval( $this->options['currency_code'] ),
          'QUANTITY' => intval( $product['quantity'] ),
          'LID' => strval( $this->options['site_id'] ),
          // 'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
          'PRODUCT_PROVIDER_CLASS' => false,
          'CATALOG_XML_ID' => intval( $product['xml_id'] ),
          'CUSTOM_PRICE' => 'Y'
        ];
        $basketItem = $basket->createItem( 'catalog', $arItem['PRODUCT_ID'] );
        $basketItem = $basketItem->setFields( $arItem );
        if (!$basketItem->isSuccess()) {
            // file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/orders/checkSJU.txt',print_r('ERROR',true).PHP_EOL,FILE_APPEND);
            // file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/orders/checkSJU.txt',print_r($basketItem->getErrorMessages(),true).PHP_EOL,FILE_APPEND);
        }
        $orderSum += floatval( $product['price'] * $product['quantity'] );
      }
      // Дополнительная информация для комментария
      // $additionalInfo = empty($orderApi['offices']) ? '' : $orderApi['offices'];

      // Работаем с заказом
      $orderBX = \Bitrix\Sale\Order::create( $this->options['site_id'], $this->options['user_id'] );
      $orderBX->setPersonTypeId( $this->options['person_type'] );
      $orderBX->setFields([
        // 'CURRENCY' => $this->options['currency_code'],
        'USER_DESCRIPTION' => "Заказ поступил с WB.\n",
        // 'USER_DESCRIPTION' => 'WB TEST',
        'COMMENTS' => 'Заказ поступил с WB ' . $this->cabinet,
        'STATUS_ID' => $this->options['default_status'],
        'EMP_STATUS_ID' => 1
      ]);
      $orderBX->setBasket( $basket );

      // Сохраненяем пользовательские свойства
      $propertyCollection = $orderBX->getPropertyCollection();
      $customerDetails = $this->getCustomerDetails( $orderApi );
      $propertyFields = [
        'MAXYSS_WB_NUMBER' => trim( $orderApi["order_id"] ),
        'MAXYSS_WB_RID' => trim( $orderApi['rid'] ),
        'MAXYSS_WB_DELIVERY_TYPE' => trim( $orderApi['delivery_type'] ),
        'MAXYSS_WB_CABINET' => trim( $this->options['cabinet'] ),
        'MAXYSS_WB_WAREHOUSEID' => trim( $orderApi['warehouse_id'] ),
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
      // $orderApi['order_bid'] = rand(111111,999999);
      // $this->writeInDatabase( $orderApi );
      $orderBX->doFinalAction(true);
      $result = $orderBX->save();
      if ( $result->isSuccess() ){
        $orderId = $orderBX->getId();
        $externalID =  $orderBX->getField('ACCOUNT_NUMBER');
        $tpId = $this->cabinet == 'WT' ? 19 : 6;
        $resss = \Bitrix\Sale\TradingPlatform\OrderTable::add(array(
            "ORDER_ID" => $orderId,
            "TRADING_PLATFORM_ID" => $tpId,
            "EXTERNAL_ORDER_ID" => $externalID
        ));

        $orderApi['order_bid'] = $orderBX->getId();
        $this->writeInDatabase( $orderApi );
        $this->writeLog("Order '{$orderApi['order_id']}' is saved successfully");
      }else{
        $this->writeLog( 'Failed to save order: ' . $result->getError() );
      }
      // var_dump('NOT CREATED NEW');
    }
  }

  private function writeInDatabase( array $order ):void
  {
    if ( empty($order) ){
      $this->writeLog('Method writeInDatabase returned an exception (empty array). Check if gathered data is valid');
      throw new \Exception("No empty array allowed. method: writeInDatabase");
    }
    $orderProducts = $order['products'];
    unset($order['products']);
    unset($order['address']);

    // Записываем заказы в таблицу
    $strSql = $this->formulateRequest( $order, $this->options['order_table_name'] );
    $this->db->Query($strSql);
    unset($strSql);
    //Записываем заказанные товары в отдельную таблицу
    foreach ( $orderProducts as $product ){
      $product['cost'] = $this->getProductCost( $product['vendor_code'], $product['quantity'] );
      $strSql = $this->formulateRequest( $product, $this->options['products_table_name'] );
      $this->db->Query($strSql);
    }
  }

  private function getProductCost( string $vendorCode, int $quantity ):float
  {
    // Возвращаем себестоимость из Моего Склада, если установлена в таблице
    // if ( $this->options['cabinet'] == 'WR' ){
    //   $strSql = "SELECT sebes FROM {$this->options['cost_ms_table_name']} WHERE model = '{$vendorCode}'";
    //   $costMS = $this->db->Query($strSql, false, $err_mess.__LINE__)->Fetch()['sebes'];
    //   if ( !empty( $costMS ) ) return $costMS;
    // }

    // Получаем количество зарезервированных единиц товара и вычитаем заказанное количество, т.к. оно включено
    $strSql = "SELECT RESERVED FROM ci_reserved WHERE ARTICLE = '{$vendorCode}'";
    $reserved = $this->db->Query($strSql, false, $err_mess.__LINE__)->Fetch()['RESERVED'] ?? 0 - $quantity;
    if ( $reserved < 0 ) $reserved = 0;

    // Получаем данные о себестоимости
    $strSql = "SELECT price, count FROM ci_price WHERE model = '{$vendorCode}' AND active_wb = 'Y' ORDER BY price ASC";
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

  private function checkIfOrderExists( array $order ):bool
  {
    if ( empty($order) ){
      $this->writeLog('Method checkIfOrderExists returned an exception (empty array). Check if gathered data is valid');
      throw new \Exception("No empty array allowed. method: checkIfOrderExists");
    }
    $strSql = "SELECT 1 FROM {$this->options['order_table_name']} WHERE order_id = '{$order['order_id']}'";
    $result = $this->db->Query($strSql, false, $err_mess.__LINE__);

    if ( $result->SelectedRowsCount() > 0 ){
      return true;
    }else{
      // Если заказа нет в таблице, но есть в битриксе, добавляем его.
      $arFilterOrder = array(
        'PROPERTY_VAL_BY_CODE_MAXYSS_WB_NUMBER' => $order['order_id'],
      );
      $rsOrders = \CSaleOrder::GetList(
        array('DATE_INSERT' => 'DESC'),
        $arFilterOrder
      );
      if ( $arOrder = $rsOrders->Fetch() ){
        $order['order_bid'] = $arOrder['ID'];
        $this->writeLog("Order '{$order['order_id']}' already was in bitrix but not in the table. Trying to add.");
        // $this->writeInDatabase( $order );

        return true;
      }
    }
    return false;
  }

  private function getItemByOfferId( string $offerId ):array
  {
    $vendorCode = end( explode('_', $offerId) );
    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_CML2_ARTICLE' => $vendorCode
    ];
    $arSelect = ['IBLOCK_ID', 'ID', 'XML_ID', 'PROPERTY_CML2_ARTICLE', 'WEIGHT', 'LENGTH', 'WIDTH', 'HEIGHT', 'VAT_ID', 'VAT_INCLUDED', 'DETAIL_PAGE_URL','NAME'];

    $result = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
    $item = [];
    while( $row = $result->GetNext() ){
    	$item = [
        'bitrix_id' => $row['ID'],
        'xml_id' => $row['XML_ID'],
        'vendor_code' => $row['PROPERTY_CML2_ARTICLE_VALUE'],
        'NAME' => $row['NAME']
      ];
    }
    return $item;
  }

  private function getCustomerDetails( array $order ):array
  {
    $result = [];
    $rsUser = CUser::GetByID( $this->options['user_id'] );
    $arUser = $rsUser->Fetch();
    if ( !empty($arUser) ){
      $result = [
        'fio' => 'WB',
        'phone' => $arUser['PERSONAL_PHONE'],
        'email' => $arUser['EMAIL'],
        'address' => empty($order['address']) ? $arUser['PERSONAL_CITY'] : $order['address']
      ];
    }
    return $result;
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
$cab = $argv[1];
( new WBOrderMain($cab) )->run();
$workers->updateStatus("N");
 ?>
