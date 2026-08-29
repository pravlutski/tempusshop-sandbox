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

class StockReturns
{
  private array $cabinets = [
    'IP' => true,
  ];
  private array $retryCodes = [
    500 => true,
    502 => true,
    429 => true,
  ];
  private int $maxAttempts = 10;
  private int $delay = 1;
  private int $maxDelay = 7;

  private string $url = 'https://api-seller.ozon.ru/v1/returns/list';

  private string $cabinet;
  private DBPanel $panel;
  private \Bitrix\Main\DB\MysqliConnection $main;

  public function __construct( string $cabinet )
  {
    if ( !$this->cabinets[$cabinet] ) throw new InvalidArgumentException("unknown cabinet");
    $this->cabinet = $cabinet;
    $this->panel = new DBPanel;
    $this->main = \Bitrix\Main\Application::getConnection();
  }

  public function run():void
  {
    $returns = array_merge( [],
      $this->getReturnsInfo('MovingToSeller'),
      $this->getReturnsInfo('MovingToOzon'),
      $this->getReturnsInfo('ReturningToSellerByCourier'),
      $this->getReturnsInfo('ArrivedAtReturnPlace'),
    );

    $items = $this->processReturns( $returns );

    $this->save( $items );
  }

  private function getReturnsInfo( string $status ):array
  {
    $data = [
      'limit' => 500,
      'filter' => [ 'visual_status_name' => $status ],
    ];
    $attempt = 1;
    $result = [];

    while ( true ){
      if ( $attempt >= $this->maxAttempts ) throw new Exception("Out of attempts limit");
      $response = $this->request( $data );
      $code = $response['code'];

      if ( $this->retryCodes[$code] ){
        $attempt++;
        sleep( 5 );
        continue;
      }
      $attempt = 1;

      if ( $code != 200 ){
        var_dump($response);
        throw new Exception("CODE [$code]. ERROR");
      }
      $result = array_merge( $result, $response['result']['returns'] );

      if ( count($response['result']['returns']) < $data['limit'] ) break;
      $data['last_id'] = end( $result )['id'];
      sleep(2);
    }

    return $result;
  }

  private function processReturns( array $returns ):array
  {
    $ms = $this->getCostFromMS();

    return array_map( function($item) use ($ms){
      $model = end( explode('_', $item['product']['offer_id']) );
      return [
        'model' => $model,
        'cost' => $ms[$model] ?? 0,
        'quantity' => $item['product']['quantity'],
        'date' => date('Y-m-d'),
      ];
    }, $returns );
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

  private function save( array $items ):void
  {
    $this->panel->insert('ozon_analytics_stock_returns', $items);
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

( new StockReturns($argv[1]) )->run();
 ?>
