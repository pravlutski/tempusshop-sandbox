<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require( 'lib/bootstrap.php' );

class ImportOrders extends ImportBase
{
  private ?ImportOrderService $ios = null;
  private ?PanelOrderService $pos = null;
  private ?BitrixOrderService $bos = null;

  public function __construct( string $cabinet )
  {
    parent::__construct( cabinet: $cabinet, module: 'orders' );

    $this->pos = new PanelOrderService( $this->data, $this->config, $this->updater );
    $this->bos = new BitrixOrderService( $this->data, $this->config );

    $this->ios = new ImportOrderService(
      data: $this->data,
      config: $this->config,
      pos: $this->pos,
      bos: $this->bos,
    );
  }

  public function run():void
  {
    CommunicationService::log('START');
    CommunicationService::updateStatus(
      text: 'Получение заказов',
      percent: 30,
      status: 'PROCESS',
      start: date('Y.m.d G:i:s')
    );
    $orders = $this->getOrders();

    CommunicationService::log( 'Got orders: ' . count($orders ?? []) );
    $this->loadSavedOrders( $orders );

    CommunicationService::updateStatus( text: "Обработка заказов", percent: 60 );
    $this->processOrders( $orders );

    CommunicationService::log('END');
    CommunicationService::updateStatus(
      text: 'Завершено',
      percent: 100,
      status: 'COMPLETED',
      end: date('Y.m.d G:i:s')
    );
  }

  private function loadSavedOrders( array $orders ):void
  {
    if ( empty($orders) ){
      CommunicationService::log('No orders to process');
      CommunicationService::log('END');
      return;
    }

    $this->pos->loadSavedOrders( array_keys($orders) );
    $this->bos->loadSavedOrders( array_keys($orders) );
  }

  private function getOrders():array
  {
    $orders = $this->fetcher->fetch();

    return $this->ios->enrichProductsWithCost( $orders );
  }

  private function processOrders( array $orders ):void
  {
    foreach ( $orders as $id => $order ){
      $isInPanelTable = $this->pos->checkIfOrderExists( $id );
      $isInBitrixTable = $this->bos->checkIfOrderExists( $id );

      $isUpdated = $this->ios->updateOrder( $order, $isInBitrixTable, $isInPanelTable );

      if ( $isUpdated ){
        CommunicationService::log( "Order {$id} was successfully updated" );
        continue;
      }
      CommunicationService::log( "Order {$id} was updated partly or was not updated at all" );

      $isCreated = $this->ios->createOrder( $order, $isInBitrixTable, $isInPanelTable );

      if ( $isCreated ){
        CommunicationService::log( "Order {$id} was successfully saved" );
        continue;
      }
      CommunicationService::log( "Order {$id} was saved partly or was not saved at all" );
    }
  }

}

$obj = new ImportOrders( 'WR' );
$obj->run();
?>
