<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}

class OzonOrderFbo {

  private $ordersApi; // Массив с заказами озона
  private $api; // Массив с данными для авторизации по апи
  private $dbLeg; // Экземпляр класса базы данных main
  private $dbPanel; // Экземпляр класса базы данных panel
  private $options; // Массив с разными параметрами для добавления заказа в битрикс
  private array $salesNames = [];

  public function __construct( $cabinet )
  {
    if ( !in_array($cabinet, ['IP', 'TI']) ) die("INVALID CABINET\n");

    CModule::IncludeModule("panel.manager");
    $this->dbPanel = new DBPanel;
    $this->dbLeg = \Bitrix\Main\Application::getConnection();
    $this->cabinet = $cabinet;

    $rows = $this->dbPanel->select(['*'], "ozon_main_settings_{$this->cabinet}")->make();
		foreach ($rows as $row) {
			$this->api[$row['name']] = $row['value'];
		}

    if ( $cabinet == "IP" ){
      $this->options = [
        'order_table_name' => 'wdhs_ozon_orders', // Таблица с данными о заказах
        'products_table_name' => 'wdhs_ozon_order_products', // Таблица с данными о товарах из заказов
        'cost_fbo_table_name' => 'ozon_fbo_sebes_IP', // Таблица с себестоимостью товаров FBO
        'log_path' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/logs/IP/' . date('Y-m-d') . '_fbo_orders.txt'
      ];
    }else{
      $this->options = [
        'order_table_name' => 'wdhs_ozon_orders_ti', // Таблица с данными о заказах
        'products_table_name' => 'wdhs_ozon_order_products_ti', // Таблица с данными о товарах из заказов
        'cost_fbo_table_name' => 'ozon_fbo_sebes_TI', // Таблица с себестоимостью товаров FBO
        'log_path' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/logs/TI/' . date('Y-m-d') . '_fbo_orders.txt'
      ];
    }
    // Важно! Если вы редактируете массив выше, редактируйте также комментарии
  }

  public function run()
  {
    $this->writeLog('START');
    $this->loadOrdersOzon();
    $this->processOrders();
    $this->writeLog('END');
  }

  private function request( $url, $headers = [], $body = '' )
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

  private function loadOrdersOzon():void
  {
    // Озон отдает время по москве, а сам принимает по гринвичу, поэтому минус три часа
    $data = [
      'filter' => [
        'since' => date( 'Y-m-d', strtotime('- 1 day') ) . 'T20:00:00Z',
        'to' => date( 'Y-m-d\TG:i:s\Z', strtotime('- 3 hours') ),
      ],
      'limit' => 1000,
    ];

    $res = $this->request(
      $this->api['api_url'] . '/v2/posting/fbo/list',
      [
        'Api-Key:' . $this->api['key'],
        'Client-Id:' . $this->api['client_id'],
        'Content-Type:application/json'
      ],
      json_encode( $data, JSON_UNESCAPED_UNICODE )
    );

    if( ! is_array( $res ) || empty( $res['result'] ) ) {
      $this->writeLog('OZON had returned no orders. Check if request body is valid or there were no orders');
      return;
    }

    $postings = $res['result'];

    $orderDataTmp = [];
    foreach( $postings as $field ){
      // Общая информация по заказу
      if ($field['order_id'] == '30624070882') {
        print_r($field);
      }
      $orderDataTmp = [
        'order_id' => $field['order_id'],
        'order_bid' => 0,
        'order_number' => $field['order_number'],
        'posting_number' => $field['posting_number'],
        'in_process_at' => date( 'Y-m-d G:i:s', strtotime($field['in_process_at']) ),
        'shipment_date' => date( 'Y-m-d G:i:s', strtotime($field['shipment_date']) ),
        'status' => $field['status'],
        'delivery_type' => 'fbo',
        'timestamp' => time(),
        'lower_barcode' => $field['barcodes']['lower_barcode']
      ];

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

    $this->writeLog('Got orders from OZON: ' . count($this->ordersApi));
  }

  private function processOrders():void
  {
    if ( empty($this->ordersApi) ){
      $this->writeLog('OZON had returned no orders. Check if gathered data is valid or there were no orders');
      return;
    }
    foreach ( $this->ordersApi as &$orderApi ){
      if ( $this->checkIfOrderExists($orderApi) ){
        $this->writeLog("Order '{$orderApi['posting_number']}' already exists");
        continue;
      }
      // Сохраняем заказ
      $this->writeLog( $orderApi['posting_number'] . ' is new. Trying to add' );
      $this->writeInDatabase( $orderApi );
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
    unset( $order['products'] );

    $strSql = $this->formulateRequest( $order, $this->options['order_table_name'] );
    // var_dump($strSql);
    $this->dbLeg->Query($strSql);
    //Записываем заказанные товары в отдельную таблицу
    foreach ( $orderProducts as $product ){
      unset( $product['xml_id'] );
      $product['cost'] = $this->getProductCost( $product['vendor_code'], $product['quantity'] );
      $strSql = $this->formulateRequest( $product, $this->options['products_table_name'] );
      // var_dump($strSql);
      $this->dbLeg->Query($strSql);
    }
  }

  private function checkIfOrderExists( array $order ):bool
  {
    if ( empty($order) ){
      $this->writeLog('Method checkIfOrderExists returned an exception (empty array). Check if gathered data is valid');
      throw new \Exception("No empty array allowed. method: checkIfOrderExists");
    }

    $strSql = "SELECT 1 FROM {$this->options['order_table_name']} WHERE posting_number = '{$order['posting_number']}'";
    $result = $this->dbLeg->Query($strSql, false, $err_mess.__LINE__);

    // Отличается от реализации в скрипте с получением ФБС заказов
    if ( $result->getSelectedRowsCount() > 0 ){
      return true;
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
    $arSelect = ['IBLOCK_ID', 'ID', 'XML_ID', 'PROPERTY_CML2_ARTICLE', 'WEIGHT', 'LENGTH', 'WIDTH', 'HEIGHT', 'VAT_ID', 'VAT_INCLUDED', 'DETAIL_PAGE_URL','NAME', 'PROPERTY_OZSB_PRICE'];

    $result = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
    $dp = $this->getDPPrices();
    $item = [];

    while( $row = $result->GetNext() ){
    	$item = [
        'bitrix_id' => $row['ID'],
        'xml_id' => $row['XML_ID'],
        'vendor_code' => $row['PROPERTY_CML2_ARTICLE_VALUE'],
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

  private function getProductCost( string $vendorCode, int $quantity = 1 ):float
  {
    $rows = $this->dbPanel->select( ['*'], $this->options['cost_fbo_table_name'] )->where( 'model', $vendorCode )->make();

    foreach ( $rows as $row ){
      return $row['sebes'];
    }

    return 0;
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
    print_r($message . PHP_EOL);
    file_put_contents( $this->options['log_path'], date('Y-m-d G:i:s') . ' --- ' . $message . PHP_EOL, FILE_APPEND );
  }

}

( new OzonOrderFbo($argv[1]) )->run();
 ?>
