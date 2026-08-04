<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class OrderCounter
{
  private $dbPanel; // Экземпляр класса базы данных panel
  private $dbLeg; // Экземпляр класса базы данных main

  private $api;
  private $header;

  private $items;
  private $logPath;

  public function __construct()
  {
    global $DB;
    $this->dbLeg = $DB;
    CModule::IncludeModule("panel.manager");
    $this->dbPanel = new DBPanel;

    $strSql = "SELECT * FROM ozon_main_settings_IP";
    $result = $this->dbPanel->query( $strSql );
    $rows = $this->dbPanel->fetchAll( $result );
  	foreach ( $rows as $row ){
  	  $this->api[ $row['name'] ] = $row['value'];
  	}

    $this->logPath = $_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/ozon/analytics/log.txt';
  }

  public function run():void
  {
    $this->writeLog('START');
    $this->getItems();
    $this->writeLog( 'Got items: ' . count($this->items) );
    $this->getOrdersFbs();
    $this->writeLog( 'Got FBS orders\' data');
    $this->getOrdersFbo();
    $this->writeLog( 'Got FBO orders\' data');
    $this->updateTable();
    $this->writeLog( 'Table successfully updated');
    $this->writeLog('END');
  }

  public function getItems():void
  {
    $date = date( 'Y-m-d', strtotime('- 1 day') );
    // $date = date( 'Y-m-d ');
    $strSql = "SELECT * FROM ozon_top_analytics WHERE date = '{$date}'";

    $result = $this->dbPanel->query($strSql);
    $rows = $this->dbPanel->fetchAll($result);
    if ( empty($rows) ){
      $this->writeLog("ozon_top_analytics is empty. Died");
      die('ozon_top_analytics is empty. Died');
    }
    foreach ($rows as $row) {
      $this->items[ $row['model'] ] = [
        'id' => $row['id'],
        'model' => $row['model'],
        'count' => 0
      ];
    }
  }

  public function getOrdersFbs():void
  {
    $date = date( 'Y-m-d', strtotime('- 1 day') );

    $strSql = "SELECT prod.vendor_code as model, prod.quantity as quantity FROM wdhs_ozon_orders_ti as ord JOIN wdhs_ozon_order_products_ti as prod ON ord.posting_number = prod.posting_number WHERE ord.in_process_at >= '{$date}'";
    $result = $this->dbLeg->Query( $strSql, false, $err_mess . __LINE__ );
    if ( $result->SelectedRowsCount() < 1 ){
      $this->writeLog("wdhs_ozon_orders_ti is empty");
      return;
    }
    while ( $row = $result->Fetch() ){
      if ( isset($this->items[$row['model']]) ){
        $this->items[$row['model']]['count'] += $row['quantity'];
      }
    }
  }

  public function getOrdersFbo():void
  {
    $data = [
      'filter' => [
        'since' => date( 'Y-m-d', strtotime('- 2 day') ) . 'T21:00:00Z',
        'to' => date( 'Y-m-d', strtotime('- 1 day') ) . 'T21:00:00Z',
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
      json_encode( $data )
    );

    if( ! is_array( $res ) || empty( $res['result'] ) ) {
      $this->writeLog('OZON had returned no orders. Check if request body is valid or there were no orders');
      return;
    }

    $postings = $res['result'];
    foreach ( $postings as $posting ){
      foreach ( $posting['products'] as $product ){
        $model = end( explode('_', $product['offer_id']) );
        if ( isset($this->items[$model]) ){
          $this->items[$model]['count'] += $product['quantity'];
        }
      }
    }
  }

  public function updateTable():void
  {
    foreach ( $this->items as $item ){
      $id = $item['id'];
      $count = $item['count'];

      $strSql = "UPDATE ozon_top_analytics SET orders_count = '{$count}' WHERE id = '{$id}'";
      $this->dbPanel->query($strSql);
    }
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

  private function writeLog( string $message ):void
  {
    file_put_contents( $this->logPath, date('Y-m-d G:i:s') . ' --- ' . $message . PHP_EOL, FILE_APPEND );
  }

}

(new OrderCounter())->run();

 ?>
