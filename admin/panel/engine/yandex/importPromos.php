<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("admin_panel_engine_yandex_importPromos_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
require( 'lib/bootstrap.php' );

class ImportPromos extends ImportBase
{
  public function __construct( string $cabinet )
  {
    parent::__construct( cabinet: $cabinet, module: 'promos' );
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

    CommunicationService::updateStatus( 'Получение данных о товарах', 25 );
    $items = $this->getItemsInfo();

    CommunicationService::log("Got active items: " . count($items ?? []));

    $promoList = $this->data->getPromosList( $this->cabinet, true );
    $promoList = $this->validatePromosActivityDate($promoList);

    $promoSettings = $this->data->settings()->getPromosSettings( $this->cabinet )[0];

    CommunicationService::updateStatus( 'Получение данных об акционных товрах', 50 );
    $promoOffers = $this->getPromoOffersInfo( array_keys($promoList) );

    CommunicationService::log("Processing offers...");

    CommunicationService::updateStatus( 'Распределение товаров по акцииям', 75 );

    $businessService = new BusinessPromoService(
      config: $this->config
    );
    $logService = new LogPromoService(
      updater: $this->updater,
      config: $this->config,
      cabinet: $this->cabinet
    );
    $requestService = new RequestPromoService(
      config: $this->config,
      api: $this->api
    );
    $businessService->initPromoSettings( $promoSettings );
    $distributed = $businessService->processPromos(
      list: $promoList,
      offers: $promoOffers,
      items: $items
    );

    CommunicationService::updateStatus( 'Сохранение результата', 80 );
    $prepared = $requestService->prepareRequestData( $distributed );

    $logService->prepare( $distributed, $promoList )->save();
    CommunicationService::updateStatus( 'Отправка запросов', 90 );
    $this->managePromos( $requestService, $prepared );
    CommunicationService::log("END");

    CommunicationService::updateStatus(
      text: 'Завершено',
      percent: 100,
      status: 'COMPLETED',
      end: date('Y.m.d G:i:s')
    );
  }

  private function getItemsInfo():array
  {
    $activeItems = $this->data->getActiveItems();
    $storeSettings = $this->data->settings()->getCampaignsMatchList($this->cabinet);
    $storeMarkups = array_column( $storeSettings, 'markup', 'warehouse' );

    $ids = array_keys( $activeItems );

    $rows = $this->data->items()->getItems(
      filter: ["ID" => $ids],
      select: ["CATALOG_PRICE_1", "PROPERTY_CML2_ARTICLE"]
    );

    foreach ( $rows as $row ){

      $store = reset( $activeItems[ $row['ID'] ]['warehouses'] );
      $markup = !empty( $storeMarkups[$store] ) ? (float) $storeMarkups[$store] : 1;

      $activeItems[ $row['ID'] ]['id'] = $row['ID'];
      $activeItems[ $row['ID'] ]['model'] = $row['PROPERTY_CML2_ARTICLE_VALUE'];
      $activeItems[ $row['ID'] ]['startPrice'] = $row['CATALOG_PRICE_1'] * $markup;
    }

    return $activeItems;
  }

  private function getPromoOffersInfo( array $list ):array
  {
    $result = [];

    foreach ( $list as $id ){
      $result[$id] = $this->fetcher->fetch( $id );
      CommunicationService::log("Got offers for promo {$id}");
    }

    return $result;
  }

  private function validatePromosActivityDate( array $promoList ):array
  {
    $result = [];
    $time = time();

    foreach ( $promoList as $id => $promo ){
      $timeStart = strtotime( $promo['date_from'] );
      $timeEnd = strtotime( $promo['date_to'] );

      if ( $timeStart > $time || $time > $timeEnd ) {
        CommunicationService::log("Promo {$id} lasts from {$promo['date_from']} to {$promo['date_to']}. Excluded");
        continue;
      }

      $result[$id] = $promo;
    }

    return $result;
  }

  private function managePromos( RequestPromoService $request, array $items ):void
  {
    foreach ( $items as $promoId => $data ){
      // $request->deletePromoOffers( $data['bad'], $promoId );
      $request->deleteAllPromoOffers( $promoId );
      $request->addPromoOffers( $data['good'], $promoId );
    }
  }

}

$obj = new ImportPromos('WR');
$obj->run();
 ?>
