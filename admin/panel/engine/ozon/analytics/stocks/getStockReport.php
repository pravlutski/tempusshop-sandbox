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

class StockReport
{
  private array $cabinets = [
    'IP' => true,
  ];
  private array $retryCodes = [
    500 => true,
    502 => true,
    429 => true,
  ];
  private int $maxAttempts = 20;
  private int $delay = 1;
  private int $maxDelay = 7;

  private string $url = 'https://api-seller.ozon.ru/v1/analytics/stocks';

  private string $cabinet;
  private DBPanel $panel;
  private \Bitrix\Main\DB\MysqliConnection $main;

  public function __construct( string $cabinet )
  {
    if ( !$this->cabinets[$cabinet] ) throw new InvalidArgumentException("Undefined cabinet");
    $this->cabinet = $cabinet;

    $this->panel = new DBPanel;
    $this->main = \Bitrix\Main\Application::getConnection();
  }

  public function run():void
  {
    file_put_contents(
      '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/start.txt',
      1,
    );
    $items = $this->getItems();
    $report = $this->getStockReport( $items );
    $this->save( $report ?? [] );
  }

  private function getItems():array
  {
    $rows = $this->panel->select(['*'], 'ozon_sku_dict_IP')->make();
    $rows = array_filter( $rows, fn($item) => $item['sku'] != 0 );
    return array_map( fn($item) => (string) $item['sku'], $rows );
  }

  private function getStockReport( array $items ):array
  {
    if ( empty($items) ) throw new InvalidArgumentException("empty items");
    $chunks = array_chunk($items, 100);
    $ms = $this->getCostFromMS();
    $result = [];

    foreach ( $chunks as $chunk ){
      $response = $this->request(['skus' => $chunk]);
      if ( $response['code'] !== 200 ) throw new Exception("Cannot get consistent data [{$response['code']}]");
      if ( empty($response['result']['items']) ){
        sleep( 1 );
        continue;
      }
      $tmp = array_map( function($item) use ($ms){
        return [
          'sku' => $item['sku'],
          'model' => end( explode('_',$item['offer_id']) ),
          'valid_stock_count' => $item['available_stock_count'],
          'transit_stock_count' => $item['transit_stock_count'],
          'transit_defect_stock_count' => $item['transit_defect_stock_count'],
          'stock_defect_stock_count' => $item['stock_defect_stock_count'],
          'return_to_seller_stock_count' => $item['return_to_seller_stock_count'],
          'return_from_customer_stock_count' => $item['return_from_customer_stock_count'],
          'requested_stock_count' => $item['requested_stock_count'],
          'other_stock_count' => $item['other_stock_count'],
          'cost' => $ms[ end( explode('_',$item['offer_id']) ) ] ?? 0,
          'date' => date('Y-m-d'),
        ];
      }, $response['result']['items'] );

      $result = array_merge( $result, $tmp );
      sleep(2);
    }

    var_dump( count($result) );
    // return $this->prepare( $result );
    return $result;
  }

  private function prepare( array $report ):array
  {
    $keys = array_keys( reset($report) );
    $excludeKeys = [
      'sku' => true,
      'model' => true,
      'cost' => true,
      'date' => true
    ];
    $result = [];

    foreach ( $report as $row ){
      if ( isset($result[ $row['sku'] ]) ){
        foreach ($keys as $key){
          if ( $excludeKeys[$key] ) continue;
          $result[ $row['sku'] ][ $key ] += $row[ $key ];
        }
        continue;
      }
      $result[ $row['sku'] ] = $row;
    }

    var_dump( count($result) );
    return array_values($result);
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

  private function save( array $report ):void
  {
    // $json = file_get_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/stockReport.json");
    // $report = json_decode($json, true);
    //
    // foreach ( $report as $row ){
    //   if ( $row['model'] == 'MTP-V006D-1B2' ){
    //     var_dump($row);
    //   }
    // }
    // die;

    $data = $this->prepare($report);
    $this->panel->insert('ozon_fbo_analytics_reports', $data );
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

  private function request( array $data ):array
  {
    if ( empty($data) ) throw new InvalidArgumentException("empty data");
    if ( empty($data['skus']) ) throw new InvalidArgumentException("empty sku list");

    $ch = curl_init( $this->url );
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $this->getHeaders(),
      CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    for ( $attempt = 1; $attempt < $this->maxAttempts; $attempt++ ){
      $res = curl_exec( $ch );
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      if ( $code == 200 ) break;

      if ( $this->retryCodes[$code] ){
        print_r( "Error {$code} [Attempt {$attempt}]:\n" . $res . PHP_EOL );

        sleep( $this->delay * $attempt <= $this->maxDelay ? $this->delay * $attempt : $maxDelay );
        continue;
      }

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

( new StockReport('IP') )->run();
 ?>
