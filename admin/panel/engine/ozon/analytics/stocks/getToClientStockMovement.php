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

class ClientStockMovement
{
  private DBPanel $panel;
  private \Bitrix\Main\DB\MysqliConnection $main;

  private array $urls = [
    'fbs' => "https://api-seller.ozon.ru/v4/posting/fbs/list",
    'fbo' => "https://api-seller.ozon.ru/v3/posting/fbo/list"
  ];
  private string $cabinet = 'IP';
  private int $maxAttempts = 3;

  public function __construct()
  {
    $this->panel = new DBPanel;
    $this->main = \Bitrix\Main\Application::getConnection();
  }

  public function run():void
  {
    $orders = $this->getOrdersAll();
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/orders.json", json_encode($orders));
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/orders.txt", print_r( $orders, true ));

    $prepared = $this->prepareToImport( $orders );
    var_dump( count($prepared) );
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/ordersPrepared.txt", print_r( $prepared, true ));

    $this->save( $prepared );
  }

  private function getOrdersAll():array
  {
    $fbs = $this->getOrders( 'fbs' );
    $fbo = $this->getOrders( 'fbo' );

    var_dump( count($fbs) );
    var_dump( count($fbo) );

    return array_merge( $fbs, $fbo );
  }

  private function save( array $data ):void
  {
    $this->panel->insert( 'ozon_analytics_move_to', $data );
  }

  private function getOrders( string $type ):array
  {
    $data = [
      'filter' => [
        'since' => date('Y-m-d\T00:00:00\Z', strtotime("-3 month")),
        'to' => date('Y-m-d\T00:00:00\Z'),
      ],
      // 'statuses' => ['cancelled', 'delivering'],
      'limit' => 100,

    ];
    $flag = true;
    $result = [];

    while ( $flag ){

      $response = $this->request( $this->urls[$type], $data );
      if ( $response['code'] != 200 ) throw new Exception("Cannot get inconsistent data");
      $result = array_merge( $result, $response['result']['postings'] );
      if ( empty($response['result']['cursor']) ) break;
      $data['cursor'] = $response['result']['cursor'];
      sleep(1);
    }

    return $this->prepare( $result, $type );
  }

  private function prepare( array $orders, string $type ):array
  {
    $result = [];
    $ms = $this->getCostFromMS();
    $fn = function($item) use ($ms) {
      return [
        'model' => end( explode('_',$item['offer_id']) ),
        'quantity' => $item['quantity'],
        'cost' => $ms[ end( explode('_',$item['offer_id']) ) ] ?? 0,
        'date' => date('Y-m-d'),
      ];
    };

    foreach ( $orders as $order ){
      $result[ $order['posting_number'] ] = [
        'posting_number' => $order['posting_number'],
        'status' => $order['status'],
        'substatus' => $order['substatus'],
        'type' => $type,
        'products' => array_map( $fn, $order['products'] ),
      ];
    }

    return array_filter( $result, fn($item) => $item['status'] == 'delivering' );
  }

  private function prepareToImport( array $orders ):array
  {
    $result = [];
    foreach ( $orders as $order ){
      foreach ( $order['products'] as $product ){
        if ( isset($result[ $order['type'] ][ $product['model'] ]) ){
          $result[ $order['type'] ][ $product['model'] ]['quantity'] += $product['quantity'];
          continue;
        }
        $result[ $order['type'] ][ $product['model'] ] = $product;
        $result[ $order['type'] ][ $product['model'] ]['type'] = $order['type'];
      }
    }
    foreach ( $result as $type => $data ){
      $result[$type] = array_values( $data );
    }

    return array_merge( ...array_values($result) );
  }

  private function getCostFromMS():array
  {
    $rows = $this->main->query("SELECT * FROM current_cost_ms");
    $result = [];

    while( $row = $rows->fetch() ){
      $result[ $row['model'] ] = $row['cost'];
    }

    return $result;
  }

  private function getHeaders():array
  {
    $rows = $this->panel->select(['*'], "ozon_main_settings_{$this->cabinet}")->make();
    $settings = array_column( $rows, 'value', 'name' );

    return [
      "Api-Key: {$settings['key']}",
      "Client-Id:{$settings['client_id']}",
      'Content-Type:application/json'
    ];
  }

  private function request( string $url, array $data ):array
  {
    if ( empty($data) ) throw new InvalidArgumentException("empty data");

    $ch = curl_init( $url );
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $this->getHeaders(),
      CURLOPT_POSTFIELDS => json_encode($data),
      CURLOPT_TIMEOUT => 30,
    ]);

    for ( $attempt = 1; $attempt < $this->maxAttempts; $attempt++ ){
      $res = curl_exec( $ch );
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      if ( $code == 200 ) break;

      print_r( "Error {$code}:\n" . $res . PHP_EOL );
      throw new Exception("Cannot get data from ozon");
    }
    curl_close( $ch );

    return [
      'raw' => $res,
      'code' => $code,
      'result' => json_decode($res, true)
    ];
  }
}

(new ClientStockMovement)->run();
 ?>
