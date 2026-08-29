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

class WBOrderFBO
{
  private array $config = [
    'tables' => [
      'orders' => 'wdhs_wb_orders',
      'products' => 'wdhs_wb_order_products',
      'cost' => 'wb_fbo',
      'settings' => 'wdhs_wb_main_settings',
      'dict' => 'wdhs_wb_props'
    ],
    'method' => 'https://statistics-api.wildberries.ru/api/v1/supplier/orders?dateFrom=%s',
  ];

  private DBPanel $panel;
  private \Bitrix\Main\DB\MysqliConnection $main;
  private string $cabinet;

  private array $dictionary = [];
  private array $reserved = [];
  private array $fbo = [];

  public function __construct( string $cabinet )
  {
    if ( !in_array($cabinet, ['WR', 'TL', 'WT']) ) throw new InvalidArgumentException('Invalid cabinet');

    $this->cabinet = $cabinet;
    $this->panel = new DBPanel;
    $this->main = \Bitrix\Main\Application::getConnection();
  }

  public function run():void
  {
    $this->getItemsDictionay( $this->main );

    $report = $this->getReportOrders(
      db: $this->main,
      cabinet: $this->cabinet
    );

    $orders = $this->processOrders(
      db: $this->main,
      report: $report
    );

  }

  // Основная магия
  private function getReportOrders( \Bitrix\Main\DB\MysqliConnection $db, string $cabinet ):array|bool
  {
    $response = $this->request(
      url: $this->config['method'],
      data: [ 'dateFrom' => date('Y-m-d\T00:00:00', strtotime('- 1 day')) ],
      headers: $this->getHeaders( $db, $cabinet ),
      method: 'GET'
    );

    if ( $response['code'] != 200 ){
      var_dump( $response );
      var_dump( $response['code'] );
      throw new Exception("Cannot get order report");
      return false;
    }

    return array_filter($response['result'], fn($item) => $item['warehouseType'] == 'Склад WB' );
  }

  private function processOrders( \Bitrix\Main\DB\MysqliConnection $db, array $report ):void
  {
    // Берем последний ид заказа фбо и инкрементируем, так как для фбо заказов не возвращается ид заказа
    // Двух миллиардов свободных идентификаторов должно на всю жизнь хватить
    $maxOrderID = $this->getLastFboId( $db ) + 1;

    foreach ( $report as $row )
    {
      if ( !$this->checkAndUpdateOrder( $row ) ) continue;

      $order = [
        'order_id' => intval( $maxOrderID ),
        'uid' => strval( $row['srid'] ),
        'rid' => strval( $row['srid'] ),
        'created_at' => date( 'Y-m-d G:i:s', strtotime($row['date']) ),
        'warehouse_id' => 0,
        'delivery_type' => 'fbo',
        'status' => ($row['isCancel'] === true) ? 'declined_by_client' : null,
        'cabinet' => $this->cabinet,
        'has_sticker' => 'N',
        'timestamp' => time(),
      ];

      $product = $this->getItemByNmid( $row['nmId'] );

      $products = [
        'bitrix_id' => $product['bitrix_id'],
        'vendor_code' => $product['vendor_code'],
        'name' => $product['NAME'],
        'quantity' => 1,
        'nmid' => $row['nmId'],
        'order_id' => $maxOrderID,
        'currency_code' => 643,
        'cost' => $this->getProductCost( $db, $product['vendor_code'] ),
        'price' => $row['priceWithDisc'],
        'finishedPrice' => $row['finishedPrice'],
      ];

      $this->writeInDatabase( $db, $order, $products );

      $maxOrderID++;
    }
  }

  // Запись в таблицу
  private function writeInDatabase( \Bitrix\Main\DB\MysqliConnection $db, array $order, array $products ):void
  {
    if ( empty($order) ) throw new InvalidArgumentException("Empty order data");
    if ( empty($products) ) throw new InvalidArgumentException("Empty products data");

    // Записываем заказы в таблицу
    $strSql = $this->formulateRequest( $order, $this->config['tables']['orders'] );
    $db->Query( $strSql );
    unset($strSql);
    //Записываем заказанные товары в отдельную таблицу
    $strSql = $this->formulateRequest( $products, $this->config['tables']['products'] );
    $db->Query( $strSql );
  }

  // Наборы данных (Важно, но не критично)
  private function getReservedItems( \Bitrix\Main\DB\MysqliConnection $db ):void
  {
    $strSql = "SELECT * FROM ci_reserved";
    $res = $db->Query( $strSql );
    $reserved = [];

    while ( $row = $res->Fetch() )
    {
      $reserved[ $row['ARTICLE'] ] = $row['RESERVED'];
    }

    $this->reserved = $reserved;
  }

  private function getFboCostData( DBPanel $db ):void
  {
    $rows = $db->select(['*'], $this->config['tables']['cost'])->make();
    $costs = [];
    foreach ($rows as $row) {
      $costs[ $row['article'] ] = $row['cost'];
    }
    $this->fbo = $costs;
  }

  // Проверки
  private function checkAndUpdateOrder( array $order ):bool
  {
    $orderDate = reset( explode('T', $order['date']) );
    $isDateValid = $this->checkCreateDate( $orderDate );
    if ( !$isDateValid ) return false;

    $isOrderExists = $this->checkIfOrderExists( $order['srid'] );
    if ( !$isOrderExists ) return true;

    $table = $this->config['tables']['orders'];
    $strSql = "SELECT status FROM {$table} WHERE rid = '{$order['srid']}'";

    $row = $this->main->query( $strSql )->fetch();
    $status = ($order['isCancel'] === true) ? 'declined_by_client' : null;

    if ( $status == $row['status'] || empty($status) ) return false;

    $strSql = "UPDATE {$table} SET status = '{$status}' WHERE rid = '{$order['srid']}'";
    $this->main->query( $strSql );

    return false;
  }

  private function checkIfOrderExists( string $rid ):bool
  {
    $table = $this->config['tables']['orders'];
    $strSql = "SELECT 1 FROM {$table} WHERE rid = '{$rid}'";

    return (bool) $this->main->Query( $strSql )->Fetch();
  }

  private function checkCreateDate( string $orderDate ):bool
  {
    return date('Y-m-d') == $orderDate || date('Y-m-d', strtotime('- 1 day')) == $orderDate;
  }

  // Всякие саппорт штуки
  private function getLastFboId( \Bitrix\Main\DB\MysqliConnection $db ):int
  {
    $table = $this->config['tables']['orders'];
    $strSql = "SELECT max(order_id) as max FROM {$table} WHERE delivery_type = 'fbo'";
    $res = $db->Query( $strSql );

    while ( $row = $res->Fetch() )
    {
      return $row['max'] ?? 1;
    }

    return 1;
  }

  private function getItemByNmid( int $nmid ):array
  {
    $bitrix_id = $this->dictionary[$nmid] ?? false;
    if ( $bitrix_id === false ) return [];

    $arFilter = [
      'IBLOCK_ID' => 16,
      'ID' => $bitrix_id
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

  private function getItemsDictionay( \Bitrix\Main\DB\MysqliConnection $db ):void
  {
    $table = $this->config['tables']['dict'];
    $strSql = "SELECT bitrix_id, nmid FROM {$table} WHERE cabinet = '{$this->cabinet}'";
    $rows = $db->Query( $strSql );
    $result = [];

    while ( $row = $rows->Fetch() ) {
      $result[ $row['nmid'] ] = $row['bitrix_id'];
    }

    $this->dictionary = $result;
  }

  private function getProductCost( \Bitrix\Main\DB\MysqliConnection $db, $vendorCode ):float
  {
    if ( !empty($this->fbo[$vendorCode]) ) return $this->fbo[$vendorCode];
	if (!$vendorCode) return 0;

    $reserved = $this->reserved[ $vendorCode ] ?? 0;
    // Получаем данные о себестоимости
    $strSql = "SELECT price, count FROM ci_price WHERE model = '{$vendorCode}' AND active_wb = 'Y' ORDER BY price ASC";
    $result = $db->Query( $strSql );

    while ( $row = $result->Fetch() )
    {
      if ( $row['count'] - $reserved <= 0 ) {
        $reserved = $row['count'] - $reserved;
        continue;
      }

      return floatval( $row['price'] );
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

  // Филиал АПИ менеджера
  private function getHeaders( Bitrix\Main\DB\MysqliConnection $db, string $cabinet ):array
  {
    $table = $this->config['tables']['settings'];
    $strSql = "SELECT api FROM {$table} WHERE cabinet = '{$cabinet}'";
    $key = $db->Query( $strSql )->Fetch()['api'];

    return [
      "Content-Type: application/json",
      "Authorization: {$key}",
    ];
  }

  private function request( string $url, array $data, array $headers, string $method ):array
  {
    $ch = curl_init( sprintf($url, $data['dateFrom']) );

    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );

    $res = curl_exec( $ch );
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close( $ch );

    return [
      'code' => $code,
      'result' => json_decode( $res, true )
    ];
  }
}

(new WBOrderFBO( $argv[1] ))->run();
 ?>
