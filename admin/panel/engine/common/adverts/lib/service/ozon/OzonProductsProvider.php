<?php
class OzonProductsProvider
{
  public function __construct(
    private DataProvider $data,
    private ApiManagerInterface $api
  ){}

  public function getCampaignProducts():array
  {
    $adverts = $this->data->getCampaigns();

    if ( !$adverts ){
      CommunicationService::log("Managable adverts are not found. Step skipped");
      return [];
    }

    $result = [];

    foreach ( $adverts as $id => $brand ){
      $response = $this->api->getCampaignProducts( $id );
      if ( $response['code'] == 404 ){
        CommunicationService::log("Advert is not found. Will be deleted from DB");
        $this->data->deleteAdvertInfo( $id );
        continue;
      }
      // Лучше ронять скрипт, чтобы предотвратить создание новых кампаний

      $collection = $response['result']['list'];
      $list = array_map( fn($item) => $item['id'], $collection );
      $items = array_map( fn($item) => 1, array_flip($list) );

      // Группируем по бренду и айди, потому что одному профилю может соответствовать несколько кампаний
      $result[ $brand ][ $id ] = $items;
      usleep( 500000 );
    }

    return $result;
  }

  public function getAllCampaignProducts():array
  {
    $advertIds = $this->getCampaignIds();
    if ( empty($advertIds) ) return [];

    foreach ( $advertIds as $id ){
      $response = $this->api->getCampaignProducts( $id );

      $list = array_map( fn($item) => $item['id'], $response['result']['list'] );
      $items = array_map( fn($item) => 1, array_flip($list) );

      $allItems[] = $items;
      usleep( 500000 );
    }

    $result = array_replace( ...$allItems );
    CommunicationService::log("Got active products: " . count($result));

    return $result;
  }

  private function getCampaignIds():array
  {
    $query = [
      'state' => 'CAMPAIGN_STATE_RUNNING',
      'page' => 1,
      'pageSize' => 100,
    ];

    $result = [];

    while ( true ){
      $response = $this->api->getCampaignsList( $query );

      if ( empty($response['result']['list']) ) break;

      $advertsBatch = array_map( fn($item) => $item['id'], $response['result']['list'] );
      $result = array_merge( $result, $advertsBatch );

      if ( count($response['result']['list']) < $query['pageSize'] ) break;
      usleep( 800000 );
    }

    CommunicationService::log("Got active adverts: " . count($result));

    return $result;
  }
}
?>
