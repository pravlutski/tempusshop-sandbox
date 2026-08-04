<?php
class AdvertServiceBase
{
  protected ?array $available = null;
  protected array $items = [];
  protected array $allItems = [];

  public array $createList = [];
  public array $updateList = [];
  public array $deleteList = [];
  public array $disableList = [];

  // $items [ 'brand' => [ 0 => [...], 1 => [...] ] ]
  // $allItems [ id => 0|1|2 ]
  // $createList [ 'brand' => [ 0 => [...], 1 => [...] ], ... ]
  // $updateList [ 'brand' => [ 'advertId' => [ 0 => [...], 1 => [...] ]] ]
  // $deleteList [ 'advertId' => [0 => [...], 1 => [...]] ]
  // $disableList [ 'advertId', '...' ]

  // 0 - will be removed
  // 1 - is in right now
  // 2 - will be added

  public function setProducts( array $items ):void
  {
    $this->items = $items;
  }

  public function manageProducts():void
  {
    $newAdverts = [];
    $outOfStockItems = [];
    $disabledAdverts = [];
    $updatedAdverts = [];

    foreach ( $this->items as $brand => $items ){
      if ( !isset($this->products[$brand]) ){
        CommunicationService::log("Advert for profile [$brand] is not found and will be created");
        $newAdverts[ $brand ] = array_chunk(
          $items,
          Config::instance()->getLimit('advertItems')
        );
        continue;
      }

      $potencialItems = array_column( $items, null, Config::instance()->getIdKey() );
      $brandAdverts = $this->products[$brand];

      $processed = $this->processProfileAdverts( $brandAdverts, $potencialItems );

      $newAdverts[ $brand ] = array_merge( $newAdverts[$brand] ?? [], $processed['newAdverts'] );

      $outOfStockItems = array_replace( $outOfStockItems, $processed['outOfStockItems'] );
      $disabledAdverts = array_merge( $disabledAdverts, $processed['disabledAdverts'] );
      $updatedAdverts[ $brand ] = $processed['updatedAdverts'];

      if ( empty($updatedAdverts[$brand]) ) unset( $updatedAdverts[$brand] );
      if ( empty($newAdverts[$brand]) ) unset( $newAdverts[$brand] );
    }

    $this->createList = $newAdverts;
    $this->deleteList = $outOfStockItems;
    $this->disableList = $disabledAdverts;
    $this->updateList = $updatedAdverts;

    CommunicationService::log( "Adverts will be created: " . TechAnalyticsService::countList($this->createList, 'create') );
    CommunicationService::log( "Adverts will be disabled: " . TechAnalyticsService::countList($this->disableList, 'disable') );
    CommunicationService::log( "Summary items will be deleted: " . TechAnalyticsService::countList($this->deleteList, 'delete') );
    CommunicationService::log( "Summary items will be added: " . TechAnalyticsService::countList($this->updateList, 'add') );
    CommunicationService::log( "Summary items will be updated: " . TechAnalyticsService::countList($this->updateList, 'update') );
  }

  private function processProfileAdverts( array $brandAdverts, array $potencial ):array
  {
    $outOfStockItems = [];
    $disabledAdverts = [];

    foreach ( $brandAdverts as $advertId => &$products ){
      $products = $this->checkIfItemInStock( $products, $potencial );
      $potencial = $this->checkIfItemAvailableToAdvertize( $potencial );

      $outOfStockItems[ $advertId ] = array_filter( $products, fn($item) => ($item === 0) );

      if ( count($outOfStockItems[$advertId]) == count($products) ){
        $disabledAdverts[] = $advertId;
        $products = [];
        unset( $brandAdverts[$advertId] );
        continue;
      }

      $products = $this->updateAdvertProducts( $products, $potencial, $advertId );
    }

    return [
      'updatedAdverts' => $brandAdverts,
      'outOfStockItems' => $outOfStockItems,
      'disabledAdverts' => $disabledAdverts,
      'newAdverts' => array_chunk( $potencial, Config::instance()->getLimit('advertItems'), true ),
    ];
  }

  private function checkIfItemInStock( array $active, array $potencial ):array
  {
    foreach ( $active as $key => &$status ){
      if ( !isset( $potencial[$key] ) ){
        CommunicationService::log( "Item [$key] is no longer available or does not satisfies conditions" );
        $status = 0;
      }
    }

    return $active;
  }

  private function checkIfItemAvailableToAdvertize( array $potencial ):array
  {
    if ( $this->availableProducts === null ) {
      CommunicationService::log("Availability check is disabled for selected paltform");
      return $potencial;
    }

    $result = [];

    foreach ( $potencial as $id => $data ){
      if ( !isset($this->availableProducts[$id]) ){
        CommunicationService::log("Item [{$id}] is not available to advertize");
        continue;
      }
      $result[$id] = $data;
    }

    return $result;
  }

  private function updateAdvertProducts( array $active, array &$potencial, string|int $advertId ):array
  {
    $updated = [];
    $key = Config::instance()->getIdKey();
    $limit = Config::instance()->getLimit('advertItems');
    $active = array_filter( $active, fn($item) => $item !== 0 );

    foreach ( $potencial as $k => $item ){
      if ( $this->allItems[ $item[$key] ] ){
        CommunicationService::log( "Item [{$item[$key]}] - is already in advert. Skipped" );
        unset( $potencial[$k] );
        continue;
      }

      if ( !$active[$item[$key]] && count($active) < $limit ){
        $active[ $item[$key] ] = 2;
        CommunicationService::log("Item [{$item[$key]}] - satisfies conditions and is not in any advert. Will be added to {$advertId}");
        unset( $potencial[$k] );
        continue;
      }

      CommunicationService::log("Item [{$item[$key]}] - satisfies conditions and is not in any advert. Will be added to new advert");
    }

    return $active;
  }
}
 ?>
