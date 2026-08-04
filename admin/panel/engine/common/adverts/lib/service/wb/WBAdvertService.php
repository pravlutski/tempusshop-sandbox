<?php
class WBAdvertService extends AdvertServiceBase implements AdvertServiceInterface
{
  private WBFinanceService $budget;

  public function __construct(
    private DataProvider $data,
    private ApiManagerInterface $api
  ){
    $auth = $this->data->getAuthData();
    $this->api->setAuthData( $auth );
  }

  public function setProducts( array $items ):void
  {
    $this->items = $items;
  }

  public function getCampaignProducts():void
  {
    $provider = new WBProductsProvider(
      data: $this->data,
      api: $this->api
    );
    $this->budget = new WBFinanceService( $this->api );

    $this->products = $provider->getCampaignProducts();
    $this->allItems = $provider->getAllCampaignProducts();
    $this->availableProducts = $provider->getAvailableProducts();
  }

  public function manageAdverts():void
  {
    $profileSettings = $this->data->getProfiles();
    $brandDictionary = $this->data->getBrandDictionary();

    $this->createAdverts( $brandDictionary, $profileSettings );
    $this->updateAdverts( $profileSettings );
    $this->disableAdverts();
  }

  private function createAdverts( array $brandDictionary, array $profileSettings ):void
  {
    if ( empty($this->createList) ){
      CommunicationService::log("List is empty. Step skipped");
      return;
    }
    $idKey = Config::instance()->getIdKey();

    foreach ( $this->createList as $brand => $batchList ){

      $profile = $profileSettings[ $brand ];

      foreach ( $batchList as $key => $items ){
        $result = $this->api->createCampaign([
          'name' => $this->buildName( $brandDictionary[$brand], $brand, $key ),
          'nms' => array_keys( $items ),
          'bid_type' => "unified",
          'payment_type' => "cpm",
        ]);

        if ( $result['code'] != 200 ) continue;

        $advertId = $result['result'];

        $this->data->setAdvertInfo(
          advertId: $advertId,
          brand: $brand,
          items: array_map(fn($item) => $item['nmid'], $items),
          key: $key
        );

        CommunicationService::log("Advert [{$advertId}]: created");
        $this->budget->refill( $advertId );
        $response = $this->api->changeCampaignActivity( ['id' => $advertId], 'enable' );

        if ( $response['code'] == 200 ){
          CommunicationService::log("Advert [{$advertId}]: enabled");
        }

        sleep( 13 );
      }
    }
  }

  private function updateAdverts( array $profileSettings ):void
  {
    // Массив может быть пустым только при условии, что нет ни одной активной автокампании
    if ( empty($this->updateList) ){
      CommunicationService::log("List is empty. Step skipped");
      return;
    }

    foreach ( $this->updateList as $brand => $adverts ){
      $items = [];
      $bids = [];
      $profile = $profileSettings[$brand];

      foreach ( $adverts as $advertId => $data ){
        $items[] = [
          'advert_id' => $advertId,
          'nms' => [
            'add' => array_keys( $data ),
            'delete' => array_keys($this->deleteList[ $advertId ] ?? [])
          ],
        ];
        $tmp = array_filter( $data, fn($item) => $item == 1 );
        $nms = array_map(function($item) use ($profile){
          return [
            'nm_id' => $item,
            'bid_kopecks' => $profile['bid'] * 100,
            'placement' => 'combined',
          ];
        }, array_keys( $tmp ));

        $bids[] = [
          'advert_id' => $advertId,
          'nm_bids' => $nms
        ];

        $this->budget->refill( $advertId );
      }

      $this->updateItems( $items, $brand );
      $this->updateBids( $bids );
    }
  }

  private function updateItems( array $data, int $brand ):void
  {
    $chunksItems = array_chunk( $data, 20 );

    foreach ( $chunksItems as $chunk ){
      $response = $this->api->editCampaignProducts( ['nms' => $chunk] );

      if ( $response['code'] != 200 ){
        sleep(2);
        continue;
      }

      $this->updateDatabase( $chunk, $brand );

      $ids = array_map( fn($item) => $item['advert_id'], $chunk );
      $advString = implode( ', ', $ids );

      CommunicationService::log("Items for advert(s) {$advString} were updated");
      sleep(2);
    }
  }

  private function updateBids( array $data ):void
  {
    $chunks = array_chunk( $data, 50 );

    foreach ( $chunks as $chunk ){
      $response = $this->api->editCampaignBids( ['bids' => $chunk] );

      if ( $response['code'] != 200 ){
        sleep(2);
        continue;
      }

      $ids = array_map( fn($item) => $item['advert_id'], $chunk );
      $advString = implode( ', ', $ids );

      CommunicationService::log("Bids for advert(s) {$advString} were updated");

      usleep( 300000 );
    }
  }

  private function updateDatabase( array $data, int $brand ):void
  {
    foreach ( $data as $adv ){
      $this->data->setAdvertInfo(
        advertId: $adv['advert_id'],
        brand: $brand,
        items: $adv['nms']['add'],
        key: null
      );
    }
  }

  private function disableAdverts():void
  {
    if ( empty($this->disableList) ){
      CommunicationService::log("List is empty. Step skipped");
      return;
    }

    foreach ( $this->disableList as $advertId ){
      $response = $this->api->changeCampaignActivity( ['id' => $advertId], 'disable' );

      if ( $response['code'] != 200 ) continue;

      $this->data->deleteAdvertInfo( advertId: $advertId, products: null );

      CommunicationService::log("Advert [{$advertId}] was ended successfully");
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
}
 ?>
