<?php
class BusinessPromoService
{
  private array $isItemInPromo = [];
  private array $settings = [];

  public function __construct(
    private ?ConfigProviderInterface $config
  ){}

  public function initPromoSettings( array $settings ):void
  {
    $this->settings = $settings;
  }

  public function processPromos( array $list, array $offers, array $items ):array
  {
    $result = [];

    foreach ( $list as $id => $data ){
      try{
        $result[ $id ] = $this->processItems(
          promo: $data,
          offers: $offers[ $id ] ?? [],
          items: $items,
        );
      } catch( EmptyPromoOffersException $e ){
        CommunicationService::log( $e->getMessage() );
        continue;
      }

      $goodCount = count( $result[$id]['good'] ?? [] );
      $badCount = count( $result[$id]['bad'] ?? [] );

      CommunicationService::log("{$data['name']}: {$goodCount} approved");
      CommunicationService::log("{$data['name']}: {$badCount} failed");
      CommunicationService::log("--------------------------------------");
    }

    return $result;
  }

  private function processItems( array $promo, array $offers, array $items ):array
  {
    if ( empty($offers) ) throw new EmptyPromoOffersException("{$promo['promo_id']} has no available offers");

    $result = [];
    $count = 0;
    foreach ( $offers as $offerId => $data ){
      if ( !isset( $items[ $offerId ] ) ) {
        $count++;
        continue;
      }

      switch ( $promo['mode'] ){
        case 'MAP':
          $tmp = $this->processMAPOffer(
            offer: $data,
            item: $items[ $offerId ] ?? [],
            promo: $promo
          );
          break;
        case 'FIX':
          $tmp = $this->processFIXOffer(
            offer: $data,
            item: $items[ $offerId ] ?? [],
            promo: $promo
          );
          break;
        case 'NoMAP':
          $tmp = $this->processNoMAPOffer(
            offer: $data,
            item: $items[ $offerId ] ?? [],
            promo: $promo
          );
          break;
        default:
          throw new UndefinedPromoModeException;
          break;
      }

      if ( $tmp['key'] == 'good' ) $this->isItemInPromo[ $tmp['offer']['id'] ] = true;
      $result[ $tmp['key'] ][] = $tmp['offer'];
    }

    return $result;
  }

  private function processMAPOffer( array $offer, array $item, array $promo ):array
  {
    $reason = 'undefined';
    $item['offer'] = $offer;

    if ( $this->isItemInPromo( $offer['id'] ) ){
      $item['reason'] = $this->config->getReason('priority');
      return [ 'key' => 'bad', 'offer' => $item ];
    }

    if ( $item['startPrice'] <= $offer['maxPromoPrice'] ){
      $item['reason'] = $this->config->getReason('map_eq');
      $item['promoPrice'] = $item['startPrice'];

      return [ 'key' => 'good', 'offer' => $item ];
    }

    $profitData = Calculator::checkProfitConditions(
      settings: $this->settings,
      cost: $item['cost'],
      price1: $offer['maxPromoPrice'],
      price2: $item['startPrice'],
      maxDiscount: $promo['discount'],
     );

    if ( $item['startPrice'] > $offer['maxPromoPrice'] && $profitData['status'] ){
      $reason = sprintf(
        $this->config->getReason('profit_good'),
        $profitData['margin'],
        $profitData['profit'],
        $profitData['discount']
      );
      $item['reason'] = $reason;
      $item['promoPrice'] = $offer['maxPromoPrice'];

      return [ 'key' => 'good', 'offer' => $item ];
    }

    $reason = sprintf(
      $this->config->getReason('profit_bad'),
      $profitData['margin'],
      $profitData['profit'],
      $profitData['discount'],
    );
    $item['reason'] = $reason;
    $item['promoPrice'] = $offer['startPrice'];

    return [ 'key' => 'bad', 'offer' => $item ];
  }

  private function processFIXOffer( array $offer, array $item, array $promo ):array
  {
    $reason = "undefined";
    $item['offer'] = $offer;
    $discount = (100 - $promo['discount']) / 100;

    if ( $this->isItemInPromo( $offer['id'] ) ){
      $item['reason'] = $this->config->getReason('priority');
      $item['promoPrice'] = $offer['promoPrice'];

      return [ 'key' => 'bad', 'offer' => $item ];
    }

    $profitData = Calculator::checkProfitConditions(
      settings: $this->settings,
      cost: $item['cost'],
      price1: $item['startPrice'] * $discount
    );

    if ( !$profitData['status'] ){
      return $this->processMAPOffer( offer: $offer, item: $item, promo: $promo );
    }

    if ( $item['startPrice'] * $discount <= $offer['maxPromoPrice'] ){
      $item['reason'] = $this->config->getReason('fix_map_eq');
      $item['promoPrice'] = $item['startPrice'] * $discount;

      return [ 'key' => 'good', 'offer' => $item ];
    }

    $item['reason'] = $this->config->getReason('fix_bad');
    $item['promoPrice'] = $item['maxPromoPrice'];

    return [ 'key' => 'bad', 'offer' => $item ];
  }

  private function processNoMAPOffer( array $offer, array $item, array $promo ):array
  {
    // Unique scenario when promo has no MAP for offers
    $item['promoPrice'] = $item['startPrice'];
    
    if ( !$this->checkIfOfferHasNoMAP($offer) ){
      $item['reason'] = $this->config->getReason('bad_mode');
      return [ 'key' => 'bad', 'offer' => $item ];
    }

    if ( $this->isItemInPromo( $offer['id'] ) ){
      $item['reason'] = $this->config->getReason('priority');
      return [ 'key' => 'bad', 'offer' => $item ];
    }

    $item['reason'] = $this->config->getReason('no_req');

    return [ 'key' => 'good', 'offer' => $item ];
  }

  private function checkIfOfferHasNoMAP( array $offer ):bool
  {
     return $offer['maxPromoPrice'] === false;
  }

  private function isItemInPromo( int $offerId ):bool
  {
    return isset( $this->isItemInPromo[$offerId] );
  }
}
 ?>
