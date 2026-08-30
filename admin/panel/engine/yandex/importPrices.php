<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("admin_panel_engine_yandex_importPrices_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
require( 'lib/bootstrap.php' );

class ImportPrices extends ImportBase
{
  private ?ImportPriceService $service = null;

  public function __construct( string $cabinet )
  {
    parent::__construct( cabinet: $cabinet, module: 'prices' );

    $this->service = new ImportPriceService(
      api: $this->api,
      data: $this->data,
      config: $this->config,
    );
  }

  public function run():void
  {
    CommunicationService::updateStatus(
      text: 'Начало работы',
      percent: 0,
      status: 'PROCESS',
      start: date('Y.m.d G:i:s')
    );
    CommunicationService::log("START");
    CommunicationService::updateStatus( text: 'Получение товаров', percent: 25);

    $items = $this->getItems();

    CommunicationService::log("Got items: " . count($items ?? []));
    CommunicationService::updateStatus( text: 'Формирование запроса', percent: 50);

    $prepared = $this->prepareItems(
      items: $items,
      dictionary: $this->service->getCampaignsSettings( $this->cabinet )
    );

    CommunicationService::log("Request array prepared");
    CommunicationService::updateStatus( text: 'Установка цен', percent: 75);

    $result = $this->sendPrices( $prepared );

    CommunicationService::log('END');
    CommunicationService::updateStatus(
      text: 'Завершено',
      percent: 100,
      status: 'COMPLETED',
      end: date('Y.m.d G:i:s')
    );
  }

  private function getItems():array
  {
    $activeItems = $this->data->getActiveItems();
    $priceProp = $this->config->getPriceProperty();
    $pricePropVal = $this->config->getPricePropertyVal();
    $result = [];

    $rows = $this->data->items()->getItems(
      filter: ['ID' => array_keys($activeItems)],
      select: [$priceProp]
    );

    foreach ( $rows as $row ){
      $result[ $row['ID'] ] = [
        'id' => $row['ID'],
        'price' => $row[ $pricePropVal ],
        'warehouses' => $activeItems[ $row['ID'] ]['warehouses'],
      ];
    }

    return $result;
  }

  private function prepareItems( array $items, array $dictionary ):array
  {
    $currency = $this->config->getCurrency();
    $result = [];

    foreach ( $items as $offerId => $item ){
      $warehouse = reset($item['warehouses']);
      $markup = $dictionary[$warehouse]['markup'];

      $result[] = [
        'offerId' => (string) $offerId,
        'price' => [
          'currencyId' => $currency,
          'value' => intval( $item['price'] * $markup )
        ]
      ];
    }

    return $result;
  }

  private function sendPrices( array $offers ):array
  {
    $limit = $this->config->getUpdateBusinessPricesLimit();
    $delay = $this->service->calcualteDelay( count($offers ?? []) );

    CommunicationService::log("Chunk size: {$limit}");
    CommunicationService::log("Delay (sec): {$delay}");

    $result = [];

    if ( count($offers) > $limit ){
      $chunks = array_chunk( $offers, $limit );

      foreach ( $chunks as $key => $chunk ){
        $tmp = $this->service->sendBatch( ['offers' => $chunk] );

        CommunicationService::log("Got response from batch {$key}:");
        CommunicationService::log( $tmp );

        $result[] = $tmp;
        sleep( $delay );
      }

      return $result;
    }

    $result = $this->service->sendBatch( ['offers' => $offers] );

    CommunicationService::log("Got response from solo batch:");
    CommunicationService::log( $result );

    return $result;
  }
}

$obj = new ImportPrices('WR');
$obj->run();
?>
