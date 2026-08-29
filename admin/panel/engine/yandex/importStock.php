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
require( 'lib/bootstrap.php' );

class ImportStock extends ImportBase
{
  private ?ImportStockService $service = null;

  public function __construct( string $cabinet )
  {
    parent::__construct( cabinet: $cabinet, module: 'stocks' );

    $this->service = new ImportStockService(
      api: $this->api,
      data: $this->data,
      config: $this->config,
    );
  }

  public function run():void
  {
    CommunicationService::log( "START" );

    CommunicationService::updateStatus(
      text: "Получение активных товаров",
      percent: 25,
      status: "PROCESS",
      start: date('Y.m.d G:i:s')
    );

    $items = $this->getItems();

    CommunicationService::updateStatus("Запрос данных маркета", 50);

    $stocks = $this->getStockState();
    CommunicationService::log( "Got info about campaigns: " . count($stocks ?? []) );
    CommunicationService::updateStatus("Акутализация остатков", 75);

    $actualStocks = $this->actualizeStockCount(
      stocks: $stocks,
      items: $items,
      dict: $this->service->getStoreDictionary( $this->cabinet ),
    );
    file_put_contents(
      "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/yandex/stocks.log",
      print_r( $actualStocks, true )
    );
    CommunicationService::updateStatus("Отправка запросов", 85);
    $this->sendData( $actualStocks );

    CommunicationService::log( "END" );
    CommunicationService::updateStatus(
      text: "Завершено",
      percent: 85,
      status: "COMPLETED",
      end: date('Y.m.d G:i:s')
    );
  }

  private function getItems():array
  {
    $activeItems = $this->data->getActiveItems();
    $itemsPrices = $this->service->getItemsPrices( array_keys( $activeItems ) );

    if ( empty($activeItems) ) {
      CommunicationService::log("No active items");
      throw new Exception("No active items");
    }
    CommunicationService::log( "Got items in stock: " . count($activeItems ?? []) );

    $result = [];

    foreach ( $activeItems as $id => $data ){
      foreach ( $data['warehouses'] as $store ){
        $result[$store][$id] = [
          'price' => $itemsPrices[$id],
        ];
      }
    }

    return $result;
  }

  private function getStockState():array
  {
    $campaigns = $this->data->settings()->getCampaignsList( $this->cabinet );
    $ids = array_map( fn($el) => $el['campaignId'], $campaigns );
    $result = [];

    foreach ( $ids as $campaignId ){
      $result[ $campaignId ] = $this->fetcher->fetch( $campaignId );
    }

    return $result;
  }

  private function actualizeStockCount( array $stocks, array $items, array $dict ):array
  {
    $result = [];

    foreach ( $stocks as $id => $offers ) {
      $wh = $dict[$id]['warehouse'];
      $actualStock = $dict[$id]['stock'] ?? $this->config->getDefaultStockValue();
      $minPrice = $dict[$id]['minPrice'];

      $result[$id] = $this->service->actualizeOffers(
        offers: $offers,
        items: $items[ $wh ] ?? [],
        actualStock: $actualStock,
        minPrice: $minPrice ?? 0
      );
      CommunicationService::log( "Campaign {$id}. Items will be updated: " . count($result[$id] ?? []) );
    }

    return $result;
  }

  private function sendData( array $data ):array
  {
    $result = [];

    foreach ( $data as $id => $offers ) {
      if ( count($offers) > $this->config->getMaxChunkSize() ) {
        $chunks = array_chunk( $offers, $this->config->getMaxChunkSize() );

        foreach ( $chunks as $key => $chunk ){
          $message = $this->service->sendBatch( ['skus' => $chunk], $id );

          CommunicationService::log("Response for {$id} (Batch {$key}): ");
          CommunicationService::log( $message );

          $result[] = $message;
        }
        continue;
      }

      if ( empty($offers) ){
        CommunicationService::log("No items for update ({$id})");
        continue;
      }

      $message = $this->service->sendBatch( ['skus' => $offers], $id );
      $result[] = $message;

      CommunicationService::log("Response for {$id}: ");
      CommunicationService::log( $message );
    }

    return $result;
  }

}

$obj = new ImportStock('WR');
$obj->run();
 ?>
