<?php
class RequestPromoService
{
  public function __construct(
    private ?ConfigProviderInterface $config,
    private ?ApiManager $api
  ){}

  public function prepareRequestData( array $promos ):array
  {
    $result = [];

    foreach ( $promos as $id => $items ){
      $result[$id] = [
        'good' => $this->prepareGoodItems( $items['good'] ?? [] ),
        'bad' => $this->prepareBadItems( $items['bad'] ?? [] ),
      ];
    }

    return $result;
  }

  public function addPromoOffers( array $items, string $promoId ):void
  {
    if ( empty($items) ){
      CommunicationService::log("No items to add to {$promoId}");
      return;
    }

    $result = [];
    $limit = $this->config->getUpdatePromoOffersLimit();

    if ( count($items) > $limit ){
      $chunks = array_chunk( $items, $limit );
      foreach ( $chunks as $key => $chunk ){
        $response = $this->api->updatePromoOffers([
          'promoId' => $promoId,
          'offers' => $chunk
        ]);
        $message = $response->getData()->decode();
        $result[] = $message;

        CommunicationService::log("Response for adding to {$promoId} (chunk #{$key}):");
        CommunicationService::log( $message );
      }
      return;
    }

    $response = $this->api->updatePromoOffers([
      'promoId' => $promoId,
      'offers' => $items
    ]);
    $message = $response->getData()->decode();
    $result[] = $message;

    CommunicationService::log("Response for adding to {$promoId}:");
    CommunicationService::log( $message );
  }

  public function deletePromoOffers( array $items, string $promoId ):void
  {
    if ( empty($items) ){
      CommunicationService::log("No items to delete from {$promoId}");
      return;
    }

    $result = [];
    $limit = $this->config->getDeletePromoOffersLimit();

    if ( count($items) > $limit ){
      $chunks = array_chunk( $items, $limit );
      foreach ( $chunks as $key => $chunk ){
        $response = $this->api->deletePromoOffers([
          'promoId' => $promoId,
          'offerIds' => $chunk
        ]);
        $message = $response->getData()->decode();
        $result[] = $message;

        CommunicationService::log("Response for deleting from {$promoId} (chunk #{$key}):");
        CommunicationService::log( $message );
      }
      return;
    }

    $response = $this->api->deletePromoOffers([
      'promoId' => $promoId,
      'offerIds' => $items
    ]);
    $message = $response->getData()->decode();
    $result[] = $message;

    CommunicationService::log("Response for deleting from {$promoId}:");
    CommunicationService::log( $message );
  }

  public function deleteAllPromoOffers( string $promoId ):void
  {
    $response = $this->api->deletePromoOffers([
      'promoId' => $promoId,
      'deleteAllOffers' => true,
    ]);
    $message = $response->getData()->decode();
    $result[] = $message;

    CommunicationService::log("Response for deleting from {$promoId}:");
    CommunicationService::log( $message );
  }

  private function prepareGoodItems( array $items ):array
  {
    $result = [];

    foreach ( $items as $item ){
      $result[] = [
        'offerId' => $item['id'],
        'params' => [
          'discountParams' => [
            'price' => intval($item['promoPrice'] / $this->config->getFakeDiscount()),
            'promoPrice' => intval($item['promoPrice'])
          ],
        ],
      ];
    }

    return $result;
  }

  private function prepareBadItems( array $items ):array
  {
    $items = $this->filterBadItems( $items );
    $result = [];

    foreach ( $items as $item ){
      $result[] = $item['id'];
    }

    return $result;
  }

  private function filterBadItems( array $items ):array
  {
    $result = [];

    foreach ( $items as $item ){
      // Item has no promoPrice got from yandex ( cause it never was in a promo ) so we should not send a request to delete this item from promo
      if ( empty($item['offer']['promoPrice']) ) continue;
      $result[] = $item;
    }

    return $result;
  }
}

 ?>
