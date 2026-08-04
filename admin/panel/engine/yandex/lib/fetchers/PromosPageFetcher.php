<?php
class PromosPageFetcher extends PageFetcherBase implements PageFetcherInterface
{
  public function fetch( ?string $id = null, ?int $limit = null, ?callable $request = null, ?callable $response = null ):array
  {
    if ( $request === null && $id === null ) {
      throw new InvalidArgumentException('Either $request or $id must be provided');
    }
    return $this->paginate(
      limit: $limit ?? $this->config->getRequestOffersLimit(),
      requestFn: $request ?? $this->getRequestCallable( $id ),
      responseFn: $response ?? $this->getResponseCallable(),
    );
  }

  private function getRequestCallable( string $id ):Closure
  {
    return function($query) use ($id) {
      return $this->api->getPromoOffers( ['promoId' => $id], $query );
    };
  }

  private function getResponseCallable():Closure
  {
    return function($data) {
      return $this->processResponse( $data );
    };
  }

  private function processResponse( array $data ):array
  {
    $stocks = $data['offers'] ?? [];
    $result = [];

    foreach ( $stocks as $row ){
      $result[ $row['offerId'] ] = [
        'id' => $row['offerId'],
        'promoPrice' => $row['params']['discountParams']['promoPrice'] ?? false,
        'maxPromoPrice' => $row['params']['discountParams']['maxPromoPrice'] ?? false,
      ];
    }

    return $result;
  }
}
 ?>
