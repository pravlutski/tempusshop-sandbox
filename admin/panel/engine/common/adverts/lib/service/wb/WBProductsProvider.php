<?php
class WBProductsProvider
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
      $response = $this->api->getCampaignProducts(['ids' => $id]);

      foreach ( $response['result']['adverts'] as $advert ){
        $products = array_map( fn($item) => $item['nm_id'], $advert['nm_settings'] );

        $items = array_map( fn($item) => 1, array_flip($products) );
        $result[$brand][$id] = $items;
      }
      usleep(300000);
    }

    return $result;
  }

  public function getAllCampaignProducts():array
  {
    $advertIds = $this->getActiveCampagins();
    if ( empty($advertIds) ) return [];

    $chunkList = array_map(
      fn($item) => implode(',', $item),
      array_chunk( $advertIds, Config::instance()->getLimit('advertItems') )
    );

    $allItems = [];

    foreach ( $chunkList as $ids ){
      $response = $this->api->getCampaignProducts(['ids' => $ids]);

      foreach ( $response['result']['adverts'] as $advert ){
        $products = array_map( fn($item) => $item['nm_id'], $advert['nm_settings'] );
        $items = array_map( fn($item) => 1, array_flip($products) );
        $allItems[] = $items;
      }

      usleep(300000);
    }

    $result = array_replace( ...$allItems );
    CommunicationService::log("Got active products: " . count($result));

    return $result;
  }

  private function getActiveCampagins():array
  {
    $response = $this->api->getCampaignsList();
    $statuses = Config::instance()->getActiveStatuses();
    $advertGroups = array_filter(
      $response['result']['adverts'],
      fn($item) => $statuses[ $item['status'] ]
    );

    $result = [];

    foreach ( $advertGroups as $advertGroup ){
      $tmp = array_map( fn($item) => $item['advertId'], $advertGroup['advert_list'] );
      $result = array_merge( $result, $tmp );
    }

    CommunicationService::log("Got active adverts: " . count($result));

    return $result;
  }

  public function getAvailableProducts():array
  {
    $data = [ Config::instance()->getSubjectId() ];
    $response = $this->api->getAvailableItems( $data );

    $result = array_map( fn($item) => $item['nm'], $response['result'] );
    $result = array_map( fn($item) => $item == 1, array_flip($result) );

    CommunicationService::log("Items available to advertize: " . count($result));

    return $result;
  }
}
 ?>
