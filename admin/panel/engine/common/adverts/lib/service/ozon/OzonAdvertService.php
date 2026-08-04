<?php
class OzonAdvertService extends AdvertServiceBase implements AdvertServiceInterface
{
  public function __construct(
    private DataProvider $data,
    private ApiManagerInterface $api
  ){
    $this->api->authorize();
  }

  public function getCampaignProducts():void
  {
    $provider = new OzonProductsProvider(
      data: $this->data,
      api: $this->api
    );

    $this->products = $provider->getCampaignProducts();
    $this->allItems = $provider->getAllCampaignProducts();
  }

  public function manageAdverts():void
  {
    $profileSettings = $this->data->getProfiles();
    $brandDictionary = $this->data->getBrandDictionary();

    $this->createAdverts( $brandDictionary, $profileSettings );
    $this->deleteItemsFromAdverts();
    $this->updateAdverts( $profileSettings, $advertNamesNumbers );
    $this->disableAdverts();
  }

  private function createAdverts( array $brandDictionary, array $profileSettings ):void
  {
    if ( empty($this->createList) ){
      CommunicationService::log("List is empty. Step skipped");
      return;
    }

    foreach ( $this->createList as $brand => $batchList ){
      $profile = $profileSettings[ $brand ];
      foreach ( $batchList as $key => $items ){
        $advertId = $this->api->createCampaign([
          'title' => $this->buildName( $brandDictionary[$brand], $brand, $key ),
          'weeklyBudget' => $this->calculateBudget( $items ),
          'placement' => "PLACEMENT_SEARCH_AND_CATEGORY",
          'productAutopilotStrategy' => 'TARGET_BIDS',
        ]);

        if ( !$advertId ) continue;

        $add = array_map(function($item) use ($profile){
          return [
            'sku' => (string) $item['sku'],
            'bid' => strval( $profile['bid'] * 1000000 )
          ];
        }, $items);

        $result = $this->api->addProducts( $advertId, ['bids' => $add] );

        if ( $result['code'] == 200 ){
          $this->data->setAdvertInfo(
            advertId: $advertId,
            brand: $brand,
            items: array_map(fn($item) => $item['sku'], $items),
            key: $key
          );
          $this->api->changeCampaignActivity($advertId, 'enable');
        }
        usleep( 800000 );
      }
    }
  }

  private function updateAdverts( array $profileSettings ):void
  {
    if ( empty($this->updateList) ){
      CommunicationService::log("List is empty. Step skipped");
      return;
    }

    foreach ( $this->updateList as $brand => $advertsList ){
      $profile = $profileSettings[ $brand ];

      foreach ( $advertsList as $advertId => $items ){
        $result = $this->api->updateParameters($advertId, [
          'weeklyBudget' => $this->calculateBudget( $items )
        ]);

        if ( $result['code'] != 200 ) continue;
        if ( empty($items) ) {
          CommunicationService::log( "Items list is empty. Nothing will be added, no bids will be updated" );
          continue;
        }

        $newItems = $this->buildAddUpdateBody( $items, $profile, 2 );

        if ( !empty($newItems) ){
          $result = $this->api->addProducts( $advertId, ['bids' => $newItems] );

          if ( $result['code'] == 200 ){
            $this->data->setAdvertInfo(
              advertId: $advertId,
              brand: $brand,
              items: array_keys($items),
              key: null
            );
            CommunicationService::log("New items were successfully added and DB updated");
          }
        }

        $updatedItems = $this->buildAddUpdateBody( $items, $profile, 1 );
        $result = $this->api->addProducts( $advertId, ['bids' => $updatedItems], "PUT" );

        if ( $result['code'] == 200 ){
          CommunicationService::log("Items' bids were successfully updated");
        }
      }

    }
  }

  private function deleteItemsFromAdverts():void
  {
    if ( empty($this->deleteList) ){
      CommunicationService::log("List is empty. Step skipped");
      return;
    }

    foreach ( $this->deleteList as $advertId => $items ){
      if ( empty($items) ) continue;
      $result = $this->api->deleteProducts( $advertId, ['sku' => array_keys($items)] );
      if ( $result['code'] == 200 ){
        $this->data->deleteAdvertInfo( $advertId, array_keys($items) );
      }
      usleep( 800000 );
    }
  }

  private function disableAdverts():void
  {
    if ( empty($this->disableList) ){
      CommunicationService::log("List is empty. Step skipped");
      return;
    }

    foreach ( $this->disableList as $advertId ){
      $result = $this->api->changeCampaignActivity( $advertId, 'disable' );

      if ( $result['code'] == 200 ){
        $this->data->deleteAdvertInfo( $advertId );
      }

      usleep( 800000 );
    }
  }

  private function buildName( string $brandName, int $brandId, string|int $number ):string
  {
    return sprintf(
      Config::instance()->getAdvertNameTemplate(),
      $brandName,
      $this->data->getAdvertNameNumber( $brandId, $number )
    );
  }

  private function buildAddUpdateBody( array $items, array $profile, int $status ):array
  {
    $items = array_filter( $items, fn($item) => $item == $status );

    return array_map(
      fn($item) => [ 'sku' => (string) $item, 'bid' => strval($profile['bid'] * 1000000) ],
      array_keys($items)
    );
  }

  private function calculateBudget( array $items ):int
  {
    return count($items) * 2000 * 1000000;
  }

}
 ?>
