<?php
class AdvertProcessManager
{
  public function __construct(
    private ?AdvertApiManager $api = null,
  ) {}

  public function distributeAllItems( array $comp, array $own, array $dict, array $priceData, array $quarantine, array $advertDict ):array
  {
    $result = [
      'bad' => [],
      'good' => [],
    ];

    $counter = 1;

    $check = [];
    foreach ( $own as $model => $price ) {
      $sku = $dict[ $model ];

      $c1 = empty( $advertDict[$sku] );
      $c2 = isset( $priceData[$model] );
      $c3 = empty( $quarantine[$model] );
      $c4 = ( $price >= AdvertConfigProvider::getMinimumPriceLimit() );
      $c5 = ( $counter <= AdvertConfigProvider::getMaxTopItemsCount() );
      $c6 = isset( $comp[$model] ) ? ( $comp[$model] >= $price ) : true;

      $key = ($c1 && $c2 && $c3 && $c4 && $c5 && $c6) ? 'good' : 'bad';

      $cond = [
        'c1' => $c1,
        'c2' => $c2,
        'c3' => $c3,
        'c4' => $c4,
        'c5' => $c5,
        'c6' => $c6,
      ];

      $result[ $key ][ $sku ] = [
        'model' => $model,
        'own_price' => $price,
        'comp_price' => $comp[ $model ],
        'sku' => $dict[ $model ],
        'status' => $key,
        'adv' => $advertDict[ $sku ],
        'reason' => $this->getReason( $cond ),
      ];
      if ( $c5 ){
        $check[$model]['c2'] = $c2;
        $check[$model]['c3'] = $c3;
        $check[$model]['c4'] = $c4;
        $check[$model]['c5'] = $c5;
        $check[$model]['c6'] = $c6;
        $check[$model]['hasComp'] = isset( $comp[$model] );
        $check[$model]['status'] = $key;
      }

      $counter++;
    }

    return $result;
  }

  public function processAdverts( array $distributed, array $advertDict ):array
  {
    $itemsBad = $this->distributeBadItems(
      dict: $advertDict,
      items: $distributed['bad']
    );

    $itemsGood = $this->distributeGoodItems(
      dict: $advertDict,
      items: $distributed['good']
    );

    foreach ( AdvertConfigProvider::getPreparedAdverts() as $advertId ) {
      $this->setAdvert(
        id: $advertId,
        good: $itemsGood[ $advertId ] ?? [],
        bad: $itemsBad[ $advertId ] ?? [],
        dict: $advertDict
      );
    }

    return $this->prepareLogData(
      good: $itemsGood,
      bad: $distributed['bad'],
      raw: $distributed,
    );
  }

  private function prepareLogData( array $good, array $bad, array $raw ):array
  {
    $result = [];
    $rawGood = $raw['good'];
    foreach ( $good as $advertId => $items ){
      $tmp = array_map( function($sku) use ($rawGood, $advertId){
        $item = $rawGood[$sku];
        $item['advertId'] = $advertId;
        return $item;
      }, $items );

      $result = array_merge($result, $tmp);
    }

    return [
      'good' => $result,
      'bad' => $bad,
    ];
  }

  private function getReason( array $cond ):string // Не самое лучшее решение
  {
    foreach ( $cond as $key => $value ){
      if ( !$value ) return AdvertConfigProvider::getReason( $key );
    }
    return '';
  }

  private function setAdvert( int $id, array $good, array $bad, array $dict ):void
  {
    var_dump( $id );

    if ( !empty( $bad ) ) {
      $response = $this->api->deleteAdvertGoods( advertId: $id, data: $bad );
      var_dump('delete');
      var_dump( $response );
    }

    // if ( empty($good) ) return;
    $body = $this->buildAddBody( dict: $dict, items: $good );
    var_dump('----------------------');
    var_dump( count( $good ) );
    $response = $this->api->editAdvertParameters(
      advertId: $id,
      data: $this->buildParametersBody( $good )
    );

    var_dump('edit');
    var_dump( $response );

    if ( !empty($body) ){
      $response = $this->api->addAdvertGoods( advertId: $id, data: $body );
      $response = $this->api->addAdvertGoods( advertId: $id, data: $body, method: "PUT" );
      // var_dump($body);
      var_dump('add');
      var_dump( $response );
      return;
    }

    var_dump( 'Items were not added in ' . $id );
  }

  private function distributeBadItems( array &$dict, array $items ):array
  {
    $result = [];

    foreach ( $items as $item )
    {
      $advertId = $dict[ $item['sku'] ] ?? false;
      if ( $advertId === false ) continue;

      $result[ $advertId ]['sku'][] = $item['sku'];
      unset( $dict[ $item['sku'] ] );
    }

    return $result;
  }

  private function distributeGoodItems( array $dict, array $items ):array
  {
    $advertGoods = [];

    $advertLimit = AdvertConfigProvider::getAdvertItemsLimit();
    $advertIds = AdvertConfigProvider::getPreparedAdverts();

    foreach ( $dict as $sku => $id ) {
      $advertGoods[ $id ][] = $sku;
    }

    $items = array_filter( $items, fn($item) => !isset($dict[$item['sku']]) );

    foreach ( $advertIds as $id ){
      $data = $advertGoods[$id] ?? [];

      if ( count($data) >= $advertLimit ) continue;

      foreach ( $items as $key => $item ){
        $data[] = $item['sku'];
        unset( $items[$key] );
        if ( count($data) >= $advertLimit ) break;
      }

      $advertGoods[$id] = $data;
    }

    return $advertGoods;
  }


  private function buildParametersBody( array $items ):array
  {
    $summaryCount = (count($items) == 0) ? 1 : count($items);
    $minBudget = AdvertConfigProvider::getMinItemBudget();
    $multiplier = AdvertConfigProvider::getBudgetMultiplier();

    return [
      'weeklyBudget' => strval( $summaryCount * $minBudget * $multiplier ),
    ];
  }

  private function buildAddBody( array $dict, array $items ):array
  {
    $result = [];
    var_dump($items);
    foreach ( $items as $sku ) {
      // if ( isset($dict[$sku]) ) continue;
      $result['bids'][] = [
        'sku' => (string) $sku,
        'bid' =>  strval(AdvertConfigProvider::getBidValue() * AdvertConfigProvider::getBudgetMultiplier()),
      ];
    }
    var_dump($result);

    return $result;
  }
}
?>
