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
require('lib/core.php');

class ImportStockV2
{
  private string $cabinet;
  private string $mode;

  private ?DBPanel $panel;
  private ?\Bitrix\Main\DB\MysqliConnection $main;

  private int $defaultStockCount = 20;
  private int $batchLimit = 100;

  public function __construct( string $cabinet, string $mode = 'AVTO' )
  {
    $this->init( $cabinet, $mode );
  }

  private function init( string $cabinet, string $mode = 'AVTO' ):void
  {
    CModule::IncludeModule('panel.manager');

    $this->main = \Bitrix\Main\Application::getConnection();
    $this->panel = new DBPanel;

    CommunicationService::initConnection(
      panel: $this->panel,
      module: 'stock_' . $cabinet
    );

    $this->data = new StocksDataProvider2(
      main: $this->main,
      panel: $this->panel,
    );
    $this->api = new StocksApiManager(
      headers: $this->data->getHeaders( $cabinet )
    );

    $this->cabinet = $cabinet;

    CommunicationService::techLog( $mode );
  }

  public function run():void
  {
    CommunicationService::log("START");

    CommunicationService::updateStatus(
      'Получаем активные товары',
      percent: 0,
      status: 'PROCESS',
      start: date('Y.m.d G:i:s')
    );
    $items = $this->getItems();

    CommunicationService::log("Got info about OUR warehouses: " . count($items ?? []));

    CommunicationService::updateStatus( text: 'Получаем остатки OZON', percent: 50 );
    $actualStock = $this->getActualStocks();
    CommunicationService::log("Got info about OZON warehouses: " . count($actualStock ?? []));

    CommunicationService::updateStatus( text: 'Подготоваливаем массив', percent: 70 );
    $forUpdate = $this->prepareUpdateArray(
      items: $items,
      actual: $actualStock
    );
    CommunicationService::log("Items for update prepared: " . count($forUpdate ?? []));
    $uniquePostiveItems = $this->calculateUniquePositiveItems( $forUpdate );
    CommunicationService::log( "Unique postive items count: " . count($uniquePostiveItems) );

    CommunicationService::updateStatus( text: 'Отправляем остатки', percent: 85 );
    $this->updateStock( $forUpdate );

    CommunicationService::updateStatus(
      'Завершено',
      percent: 100,
      status: 'COMPLETE',
      end: date('Y.m.d G:i:s')
    );
  }

  private function getItems():array
  {
    $activeItems = $this->data->getActiveItems();

    CommunicationService::log("Got active Items: " . count($activeItems ?? []));

    $dict = $this->data->getWarehouseDict( $this->cabinet );

    $result = [];

    foreach ( $activeItems as $bitrix_id => $item ){
      foreach ( $item['warehouses'] as $wh ){
        $whId = $dict[$wh];
        $result[ $whId ][ $item['offerId'] ] = ['supplier' => $item['supplier']];

      }
    }

    return $result;
  }

  private function getActualStocks():array
  {
    $warehouses = $this->data->getWarehouseDict( $this->cabinet );
    $result = [];

    foreach ( $warehouses as $name => $id ){
      $result[$id] = $this->api->getProductInfoStocks( $id );
      CommunicationService::log("Items for {$id}: " . count($result[$id] ?? []));
    }

    return $result;
  }

  private function prepareUpdateArray( array $items, array $actual ):array
  {
    $result = [];
    $fboStock = $this->data->getFboStock();

    foreach ( $actual as $warehouseId => $offers ){
      $tmp = $this->processWarehouseOffers(
        items: $items[ $warehouseId ] ?? [],
        offers: $offers,
        warehouseId: $warehouseId,
        fbo: $fboStock
      );
      var_dump($warehouseId);
      var_dump( count($tmp) );

      $result = array_merge( $result, $tmp );
    }

    file_put_contents(
      "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/stock2.txt",
      print_r( $result, true )
    );

    return $result;
  }

  private function processWarehouseOffers( array $items, array $offers, int $warehouseId, array $fbo ):array
  {
    $result = [];

    foreach ( $offers as $offer ){
      // $isActual = isset( $items[$offer['offer_id']] );

      if ( isset($items[ $offer['offer_id'] ]) ){
        $isStockSupplier =  in_array( $items[$offer['offer_id']]['supplier'], [47, 103, 129, 141, 144] );
      }else{
        $isStockSupplier = false;
      }

      $model = end( explode('_', $offer['offer_id']) );

      $isStockFbo = isset( $items[$offer['offer_id']] ) && ( isset($fbo[$model]) && $isStockSupplier );
      $isInPrice = isset( $items[$offer['offer_id']] ) && !isset($fbo[$model]);

      $expectedStock = ( ($isStockFbo || $isInPrice) ? $this->defaultStockCount : 0 );

      if ( $offer['free_stock'] == $expectedStock ) {
        unset( $items[ $offer['offer_id'] ] );
        continue;
      }

      $result[] = [
        'offer_id' => (string) $offer['offer_id'],
        'stock' => (int) $expectedStock,
        'warehouse_id' => (int) $warehouseId
      ];

      unset( $items[ $offer['offer_id'] ] );
    }

    var_dump($warehouseId);
    var_dump( count($result) );

    return $this->processRestOffers( $result, $items, $warehouseId );
  }

  private function processRestOffers( array $result, array $items, int $warehouseId ):array
  {
    if ( empty($items) ) return $result;

    foreach ( $items as $offerId => $value ){
      $result[] = [
        'offer_id' => (string) $offerId,
        'stock' => (int) $this->defaultStockCount,
        'warehouse_id' => (int) $warehouseId,
      ];
    }

    return $result;
  }

  private function updateStock( array $data = [] ):void
  {
    if ( empty($data) ){
      CommunicationService::log( "No items for update" );
      throw new Exception("No items for update");
    }

    if ( $data > $this->batchLimit ){

      $chunks = array_chunk( $data, $this->batchLimit );

      foreach ( $chunks as $key => $chunk ){
        $message = $this->sendBatch( ['stocks' => $chunk] );

        CommunicationService::log("Response for batch #{$key}:");
        CommunicationService::log( $message );

        sleep(1);
      }

      return;
    }

    $message = $this->sendBatch( ['stocks' => $data] );
    CommunicationService::log("Response for solo batch:");
    CommunicationService::log( $message );
  }

  private function sendBatch( array $data ):array
  {
    return $this->api->updateStocks( $data );
  }

  private function calculateUniquePositiveItems( array $items ):array
  {
    $positive = array_filter( $items, fn($el) => ($el['stock'] !== 0) );
    $result = [];

    foreach ( $positive as $row ){
      $result[ $row['offer_id'] ] = true;
    }

    return $result;
  }
}

$obj = new ImportStockV2(
  cabinet: $argv[1],
  mode: $argv[2]
);
$obj->run();
 ?>
